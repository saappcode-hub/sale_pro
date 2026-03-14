<!-- Updated version of add_cash_ring.blade.php with same value validation -->
<div class="modal-dialog modal-lg" role="document" style="width: 100%; max-width: 1150px;">
    <div class="modal-content">
        @php
            $isEdit = isset($cashRingData);
            $formUrl = $isEdit ? route('cash-ring.update', $cashRingData['id']) : route('cash-ring.store');
            $formMethod = $isEdit ? 'PUT' : 'POST';
            $modalTitle = $isEdit ? __('Edit Cash Ring') : __('Add Cash Ring');
        @endphp
        
        {!! Form::open(['url' => $formUrl, 'method' => $formMethod, 'id' => 'addCashRingForm', 'class' => 'form-horizontal']) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
            <h4 class="modal-title">{{ $modalTitle }}</h4>
        </div>

        <div class="modal-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- UPDATED: Made brands field optional -->
            <div class="form-group">
                {!! Form::label('brand_id', __('Brands') . ':', ['class' => 'col-sm-2 control-label']) !!}
                <div class="col-sm-10">
                    {!! Form::select('brand_id', $brands, $isEdit ? $cashRingData['brand_id'] : null, ['class' => 'form-control', 'placeholder' => 'Select Brand', 'required' => false]) !!}
                </div>
            </div>

            <div class="form-group" id="ringNameGroup">
                {!! Form::label('product_id', __('Ring Name') . ':*', ['class' => 'col-sm-2 control-label']) !!}
                <div class="col-sm-10">
                    @if($isEdit)
                        <input type="text" id="ring_name_search" name="ring_name_search" class="form-control product-input" 
                               value="{{ $cashRingData['product_name'] }} ({{ $cashRingData['product_sku'] }})" 
                               required placeholder="Search products..." autocomplete="off" disabled>
                        <input type="hidden" id="ring_name_searchId" name="product_id" value="{{ $cashRingData['product_id'] }}">
                    @else
                        <input type="text" id="ring_name_search" name="ring_name_search" class="form-control product-input" 
                               required placeholder="Search products..." autocomplete="off">
                        <input type="hidden" id="ring_name_searchId" name="product_id">
                    @endif
                    <ul id="ring_name_searchDropdown" class="list-group" style="display: none; position: absolute; z-index: 1000; width: 100%; max-height: 200px; overflow-y: auto;"></ul>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6 currency-section-col">
                    <div class="form-group">
                        <label class="control-label col-sm-4">@lang('Currency Type'):</label>
                        <div class="col-sm-2">
                            <input type="text" value="USD" class="form-control" readonly>
                        </div>
                    </div>
                    <div id="usd_currency_rows_container">
                        @if($isEdit && count($cashRingData['usd_records']) > 0)
                            @foreach($cashRingData['usd_records'] as $index => $record)
                                <div class="form-group currency-input-row" data-currency="USD">
                                    <input type="hidden" name="usd_currency_data[{{ $index }}][type_currency]" value="USD">
                                    <input type="hidden" name="usd_currency_data[{{ $index }}][id]" value="{{ $record->id }}">
                                    <div class="col-xs-5" style="padding-left: 15px; padding-right: 0;">
                                        <label style="font-size: 14px; margin-right: 5px;">@lang('Unit Values'):</label>
                                        <input type="number" name="usd_currency_data[{{ $index }}][unit_value]" 
                                               class="form-control unit-value" min="0" step="0.01" 
                                               value="{{ $record->unit_value > 0 ? number_format($record->unit_value, 2, '.', '') : '' }}"
                                               style="width: 80px; display: inline-block; height: 34px;">
                                    </div>
                                    <div class="col-xs-5" style="padding-left: 5px; padding-right: 0;">
                                        <label style="font-size: 14px; margin-right: 5px;">@lang('Redemption Values'):</label>
                                        <input type="number" name="usd_currency_data[{{ $index }}][redemption_value]" 
                                               class="form-control redemption-value" min="0" step="0.01" 
                                               value="{{ $record->redemption_value > 0 ? number_format($record->redemption_value, 2, '.', '') : '' }}"
                                               style="width: 80px; display: inline-block; height: 34px;">
                                    </div>
                                    <div class="col-xs-1 add-remove-btn-col" style="padding-left: 5px;">
                                        @if($loop->first)
                                            <button type="button" class="btn btn-primary add-currency-row" data-currency="USD" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger remove-currency-row" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="form-group currency-input-row" data-currency="USD">
                                <input type="hidden" name="usd_currency_data[0][type_currency]" value="USD">
                                <input type="hidden" name="usd_currency_data[0][id]" value="">
                                <div class="col-xs-5" style="padding-left: 15px; padding-right: 0;">
                                    <label style="font-size: 14px; margin-right: 5px;">@lang('Unit Values'):</label>
                                    <input type="number" name="usd_currency_data[0][unit_value]" class="form-control unit-value" min="0" step="0.01" style="width: 80px; display: inline-block; height: 34px;">
                                </div>
                                <div class="col-xs-5" style="padding-left: 5px; padding-right: 0;">
                                    <label style="font-size: 14px; margin-right: 5px;">@lang('Redemption Values'):</label>
                                    <input type="number" name="usd_currency_data[0][redemption_value]" class="form-control redemption-value" min="0" step="0.01" style="width: 80px; display: inline-block; height: 34px;">
                                </div>
                                <div class="col-xs-1 add-remove-btn-col" style="padding-left: 5px;">
                                    <button type="button" class="btn btn-primary add-currency-row" data-currency="USD" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-sm-6 currency-section-col">
                    <div class="form-group">
                        <label class="control-label col-sm-4">@lang('Currency Type'):</label>
                        <div class="col-sm-2">
                            <input type="text" value="RIEL" class="form-control" readonly>
                        </div>
                    </div>
                    <div id="riel_currency_rows_container">
                        @if($isEdit && count($cashRingData['riel_records']) > 0)
                            @foreach($cashRingData['riel_records'] as $index => $record)
                                <div class="form-group currency-input-row" data-currency="RIEL">
                                    <input type="hidden" name="riel_currency_data[{{ $index }}][type_currency]" value="RIEL">
                                    <input type="hidden" name="riel_currency_data[{{ $index }}][id]" value="{{ $record->id }}">
                                    <div class="col-xs-5" style="padding-left: 15px; padding-right: 0;">
                                        <label style="font-size: 14px; margin-right: 5px;">@lang('Unit Values'):</label>
                                        <input type="number" name="riel_currency_data[{{ $index }}][unit_value]" 
                                               class="form-control unit-value" min="0" step="1" 
                                               value="{{ $record->unit_value > 0 ? intval($record->unit_value) : '' }}"
                                               style="width: 80px; display: inline-block; height: 34px;">
                                    </div>
                                    <div class="col-xs-5" style="padding-left: 5px; padding-right: 0;">
                                        <label style="font-size: 14px; margin-right: 5px;">@lang('Redemption Values'):</label>
                                        <input type="number" name="riel_currency_data[{{ $index }}][redemption_value]" 
                                               class="form-control redemption-value" min="0" step="1" 
                                               value="{{ $record->redemption_value > 0 ? intval($record->redemption_value) : '' }}"
                                               style="width: 80px; display: inline-block; height: 34px;">
                                    </div>
                                    <div class="col-xs-1 add-remove-btn-col" style="padding-left: 5px;">
                                        @if($loop->first)
                                            <button type="button" class="btn btn-primary add-currency-row" data-currency="RIEL" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger remove-currency-row" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="form-group currency-input-row" data-currency="RIEL">
                                <input type="hidden" name="riel_currency_data[0][type_currency]" value="RIEL">
                                <input type="hidden" name="riel_currency_data[0][id]" value="">
                                <div class="col-xs-5" style="padding-left: 15px; padding-right: 0;">
                                    <label style="font-size: 14px; margin-right: 5px;">@lang('Unit Values'):</label>
                                    <input type="number" name="riel_currency_data[0][unit_value]" class="form-control unit-value" min="0" step="1" style="width: 80px; display: inline-block; height: 34px;">
                                </div>
                                <div class="col-xs-5" style="padding-left: 5px; padding-right: 0;">
                                    <label style="font-size: 14px; margin-right: 5px;">@lang('Redemption Values'):</label>
                                    <input type="number" name="riel_currency_data[0][redemption_value]" class="form-control redemption-value" min="0" step="1" style="width: 80px; display: inline-block; height: 34px;">
                                </div>
                                <div class="col-xs-1 add-remove-btn-col" style="padding-left: 5px;">
                                    <button type="button" class="btn btn-primary add-currency-row" data-currency="RIEL" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" id="saveButton" class="btn btn-primary" {{ $isEdit ? '' : 'disabled' }}>
                {{ $isEdit ? __('messages.update') : __('messages.save') }}
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal" style="border: 1px solid #ccc; background: transparent; color: #333;">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize form state
    function initializeForm() {
        // Check if we're in edit mode
        var isEditMode = {{ $isEdit ? 'true' : 'false' }};
        
        // Reset index counts based on current rows
        var usdIndex = $('#usd_currency_rows_container .currency-input-row').length;
        var rielIndex = $('#riel_currency_rows_container .currency-input-row').length;

        // Function to get next index for currency
        function getNextIndex(currency) {
            if (currency === 'USD') {
                return usdIndex++;
            } else {
                return rielIndex++;
            }
        }

        // NEW: Function to check for duplicate value combinations within currency type
        function checkForDuplicateCombinations() {
            var duplicateErrors = [];
            
            // Check USD rows for duplicate combinations
            var usdCombinations = [];
            var usdRows = $('#usd_currency_rows_container .currency-input-row');
            usdRows.each(function(index) {
                var $row = $(this);
                var unitValue = parseFloat($row.find('.unit-value').val()) || 0;
                var redemptionValue = parseFloat($row.find('.redemption-value').val()) || 0;
                
                if (unitValue > 0 && redemptionValue > 0) {
                    var combination = unitValue + '|' + redemptionValue;
                    
                    // Check if this combination already exists
                    var existing = usdCombinations.find(item => item.combination === combination);
                    if (existing) {
                        duplicateErrors.push(`USD: Rows ${existing.rowIndex + 1} and ${index + 1} have the same Unit Value (${unitValue}) and Redemption Value (${redemptionValue})`);
                    } else {
                        usdCombinations.push({
                            combination: combination,
                            rowIndex: index,
                            unitValue: unitValue,
                            redemptionValue: redemptionValue
                        });
                    }
                }
            });
            
            // Check RIEL rows for duplicate combinations
            var rielCombinations = [];
            var rielRows = $('#riel_currency_rows_container .currency-input-row');
            rielRows.each(function(index) {
                var $row = $(this);
                var unitValue = parseFloat($row.find('.unit-value').val()) || 0;
                var redemptionValue = parseFloat($row.find('.redemption-value').val()) || 0;
                
                if (unitValue > 0 && redemptionValue > 0) {
                    var combination = unitValue + '|' + redemptionValue;
                    
                    // Check if this combination already exists
                    var existing = rielCombinations.find(item => item.combination === combination);
                    if (existing) {
                        duplicateErrors.push(`RIEL: Rows ${existing.rowIndex + 1} and ${index + 1} have the same Unit Value (${unitValue}) and Redemption Value (${redemptionValue})`);
                    } else {
                        rielCombinations.push({
                            combination: combination,
                            rowIndex: index,
                            unitValue: unitValue,
                            redemptionValue: redemptionValue
                        });
                    }
                }
            });
            
            return duplicateErrors;
        }

        // UPDATED: Function to check form validity and enable/disable save button
        function updateSaveButtonState() {
            var productSelected = $('#ring_name_searchId').val();
            var hasCompleteRow = false;
            var hasIncompleteRow = false;
            var hasDuplicateCombinations = false;

            // Check each currency row for complete data and incomplete data
            $('.currency-input-row').each(function() {
                var $row = $(this);
                var unitValue = parseFloat($row.find('.unit-value').val()) || 0;
                var redemptionValue = parseFloat($row.find('.redemption-value').val()) || 0;

                // Check if both unit value AND redemption value are filled
                if (unitValue > 0 && redemptionValue > 0) {
                    hasCompleteRow = true;
                }

                // Check if only one field is filled (incomplete row)
                if ((unitValue > 0 && redemptionValue <= 0) || (unitValue <= 0 && redemptionValue > 0)) {
                    hasIncompleteRow = true;
                }
            });

            // NEW: Check for duplicate combinations within currency types
            var duplicateErrors = checkForDuplicateCombinations();
            if (duplicateErrors.length > 0) {
                hasDuplicateCombinations = true;
            }

            // Enable save button only if:
            // 1. Product is selected AND
            // 2. At least one row has both values filled AND
            // 3. No incomplete rows exist AND
            // 4. No duplicate combinations within currency types
            if (productSelected && hasCompleteRow && !hasIncompleteRow && !hasDuplicateCombinations) {
                $('#saveButton').prop('disabled', false);
            } else {
                $('#saveButton').prop('disabled', true);
            }

            // Debug logging
            console.log('Product Selected:', !!productSelected);
            console.log('Has Complete Row (both values):', hasCompleteRow);
            console.log('Has Incomplete Row (only one value):', hasIncompleteRow);
            console.log('Has Duplicate Combinations:', hasDuplicateCombinations);
            if (hasDuplicateCombinations) {
                console.log('Duplicate Errors:', duplicateErrors);
            }
        }

        // Add new currency row
        $(document).off('click', '.add-currency-row').on('click', '.add-currency-row', function() {
            var currency = $(this).data('currency');
            var index = getNextIndex(currency);
            var container, step, fieldName;

            if (currency === 'USD') {
                container = $('#usd_currency_rows_container');
                step = '0.01';
                fieldName = 'usd_currency_data';
            } else { // RIEL
                container = $('#riel_currency_rows_container');
                step = '1';
                fieldName = 'riel_currency_data';
            }

            var newRow = `
                <div class="form-group currency-input-row" data-currency="${currency}">
                    <input type="hidden" name="${fieldName}[${index}][type_currency]" value="${currency}">
                    <input type="hidden" name="${fieldName}[${index}][id]" value="">
                    <div class="col-xs-5" style="padding-left: 15px; padding-right: 0;">
                        <label style="font-size: 14px; margin-right: 5px;">@lang('Unit Values'):</label>
                        <input type="number" name="${fieldName}[${index}][unit_value]" class="form-control unit-value" min="0" step="${step}" style="width: 80px; display: inline-block; height: 34px;">
                    </div>
                    <div class="col-xs-5" style="padding-left: 5px; padding-right: 0;">
                        <label style="font-size: 14px; margin-right: 5px;">@lang('Redemption Values'):</label>
                        <input type="number" name="${fieldName}[${index}][redemption_value]" class="form-control redemption-value" min="0" step="${step}" style="width: 80px; display: inline-block; height: 34px;">
                    </div>
                    <div class="col-xs-1 add-remove-btn-col" style="padding-left: 5px;">
                        <button type="button" class="btn btn-danger remove-currency-row" style="height: 34px; width: 30px; padding: 0; line-height: 34px;">
                            <i class="fa fa-minus"></i>
                        </button>
                    </div>
                </div>`;

            container.append(newRow);
            updateSaveButtonState();
        });

        // Remove currency row
        $(document).off('click', '.remove-currency-row').on('click', '.remove-currency-row', function() {
            var currencyRow = $(this).closest('.currency-input-row');
            currencyRow.remove();
            updateSaveButtonState();
        });

        // Product search functionality
        $('#ring_name_search').off('input').on('input', function() {
            var query = $(this).val();
            var dropdown = $('#ring_name_searchDropdown');

            if (query.length >= 2) {
                $.ajax({
                    url: '{{ route("search-product-ring-cash") }}',
                    type: 'GET',
                    data: { q: query },
                    success: function(data) {
                        dropdown.empty();

                        if (data.length > 0) {
                            $.each(data, function(index, product) {
                                dropdown.append('<li class="list-group-item product-item" data-id="' + product.id + '" data-name="' + product.name + '" data-sku="' + product.sku + '" style="cursor: pointer; padding: 8px 15px; border-bottom: 1px solid #ddd;">' + product.name + ' (' + product.sku + ')</li>');
                            });
                            dropdown.show();
                        } else {
                            dropdown.append('<li class="list-group-item" style="padding: 8px 15px;">No results found</li>').show();
                        }
                    },
                    error: function() {
                        alert("Error fetching products. Please try again.");
                        dropdown.hide();
                    }
                });
            } else {
                dropdown.hide();
            }
        });

        // Handle selection from dropdown
        $(document).off('click', '.product-item').on('click', '.product-item', function() {
            var selectedProduct = $(this).data('name') + ' (' + $(this).data('sku') + ')';
            var productId = $(this).data('id');

            $('#ring_name_search').val(selectedProduct);
            $('#ring_name_searchId').val(productId);
            $('#ring_name_searchDropdown').hide();
            updateSaveButtonState();
        });

        // UPDATED: Update save button state on input changes
        $(document).off('input change', '.unit-value, .redemption-value').on('input change', '.unit-value, .redemption-value', function() {
            updateSaveButtonState();
        });

        // UPDATED: Form validation before submit - Added same value validation
        $('#addCashRingForm').off('submit').on('submit', function(e) {
            var productSelected = $('#ring_name_searchId').val();
            var hasCompleteRow = false;
            var hasIncompleteRow = false;
            var validationErrors = [];

            if (!productSelected) {
                e.preventDefault();
                alert("Please select a ring name.");
                return false;
            }

            // Check for duplicate combinations
            var duplicateErrors = checkForDuplicateCombinations();
            if (duplicateErrors.length > 0) {
                e.preventDefault();
                alert("Duplicate Combination Errors:\n" + duplicateErrors.join("\n"));
                return false;
            }

            // Check each row for complete and incomplete data
            $('.currency-input-row').each(function(rowIndex) {
                var $row = $(this);
                var currency = $row.data('currency');
                var unitValue = parseFloat($row.find('.unit-value').val()) || 0;
                var redemptionValue = parseFloat($row.find('.redemption-value').val()) || 0;

                // Check if both values are provided (both must be > 0)
                if (unitValue > 0 && redemptionValue > 0) {
                    hasCompleteRow = true;
                }

                // Check for incomplete rows (only one field filled)
                if ((unitValue > 0 && redemptionValue <= 0) || (unitValue <= 0 && redemptionValue > 0)) {
                    hasIncompleteRow = true;
                    validationErrors.push(`${currency} Row ${rowIndex + 1}: Both Unit Value and Redemption Value must be filled together.`);
                }
            });

            // Check for validation errors
            if (validationErrors.length > 0) {
                e.preventDefault();
                alert("Validation Errors:\n" + validationErrors.join("\n"));
                return false;
            }

            // Must have at least one complete row and no incomplete rows
            if (!hasCompleteRow) {
                e.preventDefault();
                alert("At least one row must have both Unit Value and Redemption Value filled.");
                return false;
            }

            if (hasIncompleteRow) {
                e.preventDefault();
                alert("All filled rows must have both Unit Value and Redemption Value. Please complete all partially filled rows.");
                return false;
            }

            // Log form data for debugging
            console.log('Form is being submitted...');
            var formData = new FormData(this);
            for (var [key, value] of formData.entries()) {
                if (key.includes('currency_data')) {
                    console.log(key + ': ' + value);
                }
            }
        });

        // Initial check
        updateSaveButtonState();
    }

    // Initialize form
    initializeForm();

    // Hide dropdown when clicked outside
    $(document).on('click', function(event) {
        if (!$(event.target).closest('#ring_name_search').length && !$(event.target).closest('#ring_name_searchDropdown').length) {
            $('#ring_name_searchDropdown').hide();
        }
    });
});
</script>

<style>
/* Existing styles */
.control-label {
    text-align: left !important;
}

.form-control {
    text-align: left !important;
    /* Remove spinner arrows */
    -webkit-appearance: none;
    -moz-appearance: textfield;
}

.currency-input-row .col-xs-5 {
    padding-left: 0;
}

.currency-input-row .col-xs-1 {
    text-align: left; /* Changed to left to bring button closer */
    padding-right: 0;
}

/* Updated styles for alignment and button size */
.currency-input-row label {
    font-size: 14px; /* Match the label size */
}

.currency-input-row .col-xs-5 input {
    width: 80px; /* Narrower input columns */
    padding: 4px; /* Reduced padding for smaller size */
    height: 34px; /* Ensure consistent height */
}

/* Ensure proper spacing between elements */
.currency-input-row .col-xs-5,
.currency-input-row .col-xs-1 {
    display: inline-block;
    vertical-align: middle;
}

/* Style for the button to match input height */
.currency-input-row .add-currency-row,
.currency-input-row .remove-currency-row {
    height: 34px; /* Match the height of the input fields */
    width: 30px; /* Set a fixed width for consistency */
    padding: 0; /* Remove default padding to center the icon */
    line-height: 34px; /* Center the icon vertically */
    margin-left: 0; /* Remove any default margin */
}

/* Hide spinner arrows for number inputs */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

/* Style for disabled save button */
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Style for error messages */
.alert-danger {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ebccd1;
    border-radius: 4px;
    background-color: #f2dede;
    color: #a94442;
}

/* Product dropdown styles */
#ring_name_searchDropdown {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

#ring_name_searchDropdown .list-group-item {
    border: none;
    border-bottom: 1px solid #eee;
}

#ring_name_searchDropdown .list-group-item:hover {
    background-color: #f5f5f5;
}
</style>