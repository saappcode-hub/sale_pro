<!-- Modal content that gets loaded via AJAX -->
@if(isset($ringUnitData))
    {!! Form::open(['url' => route('ring-units.update', $ringUnitData['id']), 'method' => 'put', 'id' => 'addRingUnitsForm', 'class' => 'form-horizontal']) !!}
@else
    {!! Form::open(['url' => action([\App\Http\Controllers\RewardExchangeController::class, 'storeRingUnits']), 'method' => 'post', 'id' => 'addRingUnitsForm', 'class' => 'form-horizontal']) !!}
@endif

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">×</span>
    </button>
    <h4 class="modal-title">@lang(isset($ringUnitData) ? 'Edit Ring Units' : 'Add Ring Units')</h4>
</div>

<div class="modal-body">
    <!-- Ring Name Search Field -->
    <div class="form-group" id="ringNameGroup">
        {!! Form::label('ring_name', __('Ring Name') . ':*', ['class' => 'col-sm-3 control-label']) !!}
        <div class="col-sm-9">
            <input type="text" id="ring_name_search" name="ring_name_search" class="form-control product-input" required placeholder="Search products..." autocomplete="off" @if(isset($ringUnitData)) value="{{ $ringUnitData['ring_name'] }}" readonly @endif>
            <input type="hidden" id="ring_name_searchId" name="ring_name" @if(isset($ringUnitData)) value="{{ $ringUnitData['product_id'] }}" @endif>
            <ul id="ring_name_searchDropdown" class="list-group" style="display: none;"></ul>
        </div>
    </div>

    <!-- Ring Unit Values Field -->
    @if(isset($ringUnitData) && !empty($ringUnitData['ring_values']))
        @foreach($ringUnitData['ring_values'] as $index => $ringUnit)
            <div class="form-group">
                <label class="col-sm-3 control-label">@lang('Unit Values'):*</label>
                <div class="col-sm-7">
                    {!! Form::number('ring_values[]', $ringUnit['value'], ['class' => 'form-control ring-value-input', 'required', 'min' => 0, 'readonly' => $ringUnit['is_readonly'] ? true : false]) !!}
                    {!! Form::hidden('ring_unit_ids[]', $ringUnit['id']) !!}
                    <span class="text-danger error-message" style="display: none;"></span>
                </div>
                <div class="col-sm-2">
                    @if($index == 0)
                        <button type="button" class="btn btn-primary" id="add_ring_value">+</button>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div class="form-group">
            <label class="col-sm-3 control-label">@lang('Unit Values'):*</label>
            <div class="col-sm-7">
                {!! Form::number('ring_values[]', 1, ['class' => 'form-control ring-value-input', 'required', 'min' => 0, 'readonly' => true]) !!}
                <span class="text-danger error-message" style="display: none;"></span>
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-primary" id="add_ring_value">+</button>
            </div>
        </div>
    @endif

    <div id="ring_values"></div>
</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-primary">@lang('messages.save')</button>
    <button type="button" class="btn btn-default" data-dismiss="modal" style="border: 1px solid #ccc; background: transparent; color: #333;">@lang('messages.close')</button>
</div>

{!! Form::close() !!}

<script>
// This script runs when the modal content is loaded
$(document).ready(function() {
    // Function to check for duplicate ring values
    function checkDuplicateRingValues() {
        var ringValues = $('input[name="ring_values[]"]').map(function() {
            return $(this).val();
        }).get();

        var duplicates = [];
        var seen = new Set();

        ringValues.forEach(function(value, index) {
            if (seen.has(value)) {
                duplicates.push({ value: value, index: index });
            } else {
                seen.add(value);
            }
        });

        $('.error-message').hide().text('');

        duplicates.forEach(function(duplicate) {
            var input = $('input[name="ring_values[]"]').eq(duplicate.index);
            input.next('.error-message').text('This value is already used.').show();
        });

        return duplicates.length === 0;
    }

    // Add new ring value input field
    $('#add_ring_value').off('click').on('click', function() {
        var newRow = `
            <div class="form-group">
                <div class="col-sm-3"></div>
                <div class="col-sm-7">
                    <input type="number" name="ring_values[]" class="form-control ring-value-input" required min="0">
                    <input type="hidden" name="ring_unit_ids[]" value="">
                    <span class="text-danger error-message" style="display: none;"></span>
                </div>
                <div class="col-sm-2">
                    <button type="button" class="btn btn-danger btn-square remove-ring-value">-</button>
                </div>
            </div>`;
        $('#ring_values').append(newRow);
        checkDuplicateRingValues();
    });

    // Remove ring value input field
    $(document).off('click', '.remove-ring-value').on('click', '.remove-ring-value', function() {
        if ($('input[name="ring_values[]"]').length > 1) {
            $(this).closest('.form-group').remove();
            checkDuplicateRingValues();
        } else {
            swal("Warning!", "At least one ring value is required.", "warning");
        }
    });

    // Check for duplicates on input change
    $(document).off('input', '.ring-value-input').on('input', '.ring-value-input', function() {
        checkDuplicateRingValues();
    });

    // Client-side validation before form submission
    $('#addRingUnitsForm').off('submit').on('submit', function(e) {
        if ($('#ring_name_search').prop('readonly')) {
            if (!checkDuplicateRingValues()) {
                e.preventDefault();
                swal("Error!", "Duplicate ring unit values are not allowed.", "error");
                return false;
            }
            return true;
        }

        if (!$('#ring_name_searchId').val()) {
            e.preventDefault();
            swal("Error!", "Please select a product.", "error");
            return false;
        }

        var ringValues = $('input[name="ring_values[]"]').map(function() {
            return $(this).val();
        }).get();

        if (ringValues.length === 0 || ringValues.some(value => !value || value < 0)) {
            e.preventDefault();
            swal("Error!", "Please provide valid ring unit values (positive numbers).", "error");
            return false;
        }

        if (!checkDuplicateRingValues()) {
            e.preventDefault();
            swal("Error!", "Duplicate ring unit values are not allowed.", "error");
            return false;
        }

        var productId = $('#ring_name_searchId').val();
        var shouldSubmit = false;

        $.ajax({
            url: '{{ route("ring-units.check-product") }}',
            type: 'GET',
            data: { product_id: productId },
            async: false,
            success: function(data) {
                if (data.exists) {
                    e.preventDefault();
                    swal("Error!", "A Ring Unit for this product already exists!", "error");
                    shouldSubmit = false;
                } else {
                    shouldSubmit = true;
                }
            },
            error: function() {
                e.preventDefault();
                swal("Error!", "Unable to verify product existence. Please try again.", "error");
                shouldSubmit = false;
            }
        });

        return shouldSubmit;
    });

    checkDuplicateRingValues();
});
</script>

<style>
.list-group {
    max-height: 200px;
    overflow-y: auto;
    position: absolute;
    width: 94%;
    z-index: 1000;
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.list-group-item {
    padding: 10px 15px;
    border-bottom: 1px solid #e9ecef;
    display: block;
    text-decoration: none;
    color: #333;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.error-message {
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

input[readonly] {
    background-color: #f0f0f0;
    cursor: not-allowed;
}
</style>