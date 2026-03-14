@extends('layouts.app')

@section('title', __('Add Rewards Exchange'))

@section('content')
<section class="content-header">
    <h1>{{ __('Add Rewards Exchange') }}</h1>
</section>
<style>
    /* Custom styles */
    .set-quantity {
        width: 80px; /* Increased width */
        text-align: center; /* Center text */
        padding: 5px; /* Increased padding */
        font-size: 16px; /* Increased font size */
    }

    .set-btn {
        width: 30px; /* Fixed width for buttons */
        height: 30px; /* Fixed height for buttons */
        font-size: 20px; /* Font size */
    }

    /* Remove default arrow buttons from input */
    input[type=number] {
        -moz-appearance: textfield; /* Firefox */
    }
    
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none; /* Chrome, Safari, Edge */
        margin: 0; /* Remove margin */
    }

    /* Increase header and cell padding */
    th, td {
        padding: 12px; /* Increased padding */
        font-size: 16px; /* Increased font size */
    }

    th {
        font-weight: bold; /* Make header bold */
    }
    .stock-ring-balance {
        width: 100px;
        text-align: center;
        font-size: 16px;
        padding: 5px;
    }

    /* Remove custom styling for sale order - let Bootstrap handle it */
</style>
{!! Form::open(['url' => route('sales_reward.store'), 'method' => 'POST', 'id' => 'reward-form']) !!}
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @if(count($business_locations) > 0)
                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-map-marker"></i>
                                </span>
                                {!! Form::select('select_location_id', $business_locations, null, ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required', 'autofocus']) !!}
                                <span class="input-group-addon">
                                    @show_tooltip(__('tooltip.sale_location'))
                                </span> 
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('sell_list_filter_contact_id', __('Customer') . ':') !!}
                                        {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('sale_order', __('Sale Order') . ':') !!}
                                        <div class="input-group">
                                            {!! Form::text('sale_order', null, ['class' => 'form-control', 'placeholder' => __('Enter Sale Order'), 'required']) !!}
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-info" id="check-transaction">
                                                    <i class="fas fa-check"></i> Check
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('transaction_date', __('Transaction Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', null, ['class' => 'form-control', 'id' => 'transaction_date', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('status', __('Status') . ':') !!}
                                        {!! Form::select('status', ['all' => __('All'), 'pending' => __('Pending'), 'completed' => __('Completed')], 'all', ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'status']) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table table-bordered" id="exchange-product-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Exchange Product') }}</th>
                                            <th>{{ __('Exchange Quantity') }}</th>
                                            <th>{{ __('Price') }}</th>
                                            <th>{{ __('Receive Quantity') }}</th>
                                            <th>{{ __('Used Ring Balance') }}</th>
                                            <th>{{ __('Quantity') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic rows will be added here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('note', __('Note') . ':') !!}
                                    {!! Form::textarea('note', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter Note')]) !!}
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            {!! Form::submit(__('Submit'), ['class' => 'btn btn-primary', 'id' => 'submit-button', 'disabled' => true]) !!}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
{!! Form::close() !!}
@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        // Initialize form state
        let isChecked = false;

        // Reset form fields on page load to prevent stale data after refresh
        function resetForm() {
            $('input[name="sale_order"]').val('');
            $('select[name="sell_list_filter_contact_id"]').val(null).trigger('change');
            $('#status').val('all').trigger('change');
            $('#exchange-product-table tbody').empty();
            $('#submit-button').prop('disabled', true);
            isChecked = false;
        }

        // Call resetForm on page load
        resetForm();

        // Attach event listener to set quantity inputs to revalidate stock ring balance
        $(document).on('input', 'input[name="set_quantity[]"]', function() {
            const row = $(this).closest('tr');
            const exchangeQuantity = parseInt(row.find('input[name="exchange_quantity[]"]').val(), 10) || 0;
            const setQuantity = parseInt($(this).val(), 10) || 0;

            // Calculate stockRingBalance
            const stockRingBalance = exchangeQuantity * setQuantity;

            // Update the stockRingBalance display as text
            row.find('.stock-ring-balance-text').text(stockRingBalance);

            // Update the hidden input field for stockRingBalance
            row.find('input[name="stock_ring_balance[]"]').val(stockRingBalance);
        });

        // Attach event listeners for increment and decrement buttons
        $(document).on('click', '.set-btn', function() {
            var action = $(this).data('action');
            var index = $(this).data('index');
            var inputField = $('input[name="set_quantity[]"]').eq(index);
            var currentValue = parseInt(inputField.val()) || 0;

            if (action === 'increment') {
                inputField.val(currentValue + 1);
            } else if (action === 'decrement' && currentValue > 0) {
                inputField.val(currentValue - 1);
            }

            // Trigger input event to recalculate stock ring balance
            inputField.trigger('input');
        });

        // Initialize the date picker for Transaction Date with time picker enabled
        var currentDateTime = moment().format('YYYY-MM-DD HH:mm:ss');
        $('#transaction_date').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            locale: {
                format: 'YYYY-MM-DD HH:mm:ss'
            },
            startDate: currentDateTime,
            autoUpdateInput: true
        });

        // Set the input value to the current date if not selected
        $('#transaction_date').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm:ss'));
        });

        // On form submit, ensure date, status, and quantities are valid
        $('#reward-form').on('submit', function(e) {
            if (!isChecked) {
                e.preventDefault();
                alert('Please click Check to validate the transaction before submitting.');
                return;
            }

            if (!$('#transaction_date').val()) {
                e.preventDefault();
                alert('Please select a transaction date before submitting.');
                return;
            }

            var status = $('#status').val();
            if (status === "all" || status === "") {
                e.preventDefault();
                alert('Please select a valid status (Pending or Completed) before submitting.');
                return;
            }

            var allQuantitiesFilled = true;
            $('input[name="set_quantity[]"]').each(function() {
                if ($(this).val() === "" || $(this).val() < 0) {
                    allQuantitiesFilled = false;
                    return false;
                }
            });

            if (!allQuantitiesFilled) {
                e.preventDefault();
                alert('Please fill in all quantities with valid numbers before submitting.');
                return;
            }

            // ONLY DISABLE BUTTON IF ALL VALIDATIONS PASS
            $('#submit-button').prop('disabled', true).text('Processing...');
        });

        $('#check-transaction').on('click', function() {
            var saleorder = $('input[name="sale_order"]').val();

            if (!saleorder) {
                alert('Please enter a sale order number.');
                $('#submit-button').prop('disabled', true);
                isChecked = false;
                return;
            }

            $.ajax({
                url: '{{ route("sales_reward.check_transaction") }}',
                type: 'GET',
                data: {
                    saleorder: saleorder,
                    context: 'create'
                },
                success: function(response) {
                    $('#exchange-product-table tbody').empty();
                    if (response.rewards && response.rewards.length > 0) {
                        response.rewards.forEach(function(reward, index) {
                            var setQuantity = response.set_quantity[index] || 0;
                            var productMultiplier = reward.exchange_quantity * setQuantity;
                            var stockRingBalance = reward.stock_ring_balance ?? 0;
                            var displayStockRingBalance = Math.min(productMultiplier, stockRingBalance);

                            if (stockRingBalance === null || stockRingBalance === 0 || stockRingBalance < 0) {
                                displayStockRingBalance = 0;
                            }

                            var row = `
                                <tr style="background-color: whitesmoke;">
                                    <td>${reward.exchange_product}</td>
                                    <td>${reward.exchange_quantity}</td>
                                    <td>$${reward.amount}</td>
                                    <td>${reward.receive_quantity}</td>
                                    <td class="stock-ring-balance-text">${displayStockRingBalance} Ring</td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <button type="button" class="btn btn-danger btn-xs set-btn" data-index="${index}" data-action="decrement" disabled>-</button>
                                            <input type="number" class="form-control set-quantity" name="set_quantity[]" value="${setQuantity}" min="0" disabled>
                                            <input type="hidden" name="set_quantity[]" value="${setQuantity}">
                                            <button type="button" class="btn btn-success btn-xs set-btn" data-index="${index}" data-action="increment" disabled>+</button>
                                        </div>
                                    </td>
                                    <input type="hidden" name="product_for_sale_id[]" value="${reward.product_for_sale_id}">
                                    <input type="hidden" name="exchange_product_id[]" value="${reward.exchange_product_id}">
                                    <input type="hidden" name="receive_product_id[]" value="${reward.receive_product_id}">
                                    <input type="hidden" name="variation_id[]" value="${reward.variation_id}">
                                    <input type="hidden" name="amount[]" value="${reward.amount}">
                                    <input type="hidden" name="exchange_quantity[]" value="${reward.exchange_quantity}">
                                    <input type="hidden" name="receive_quantity[]" value="${reward.receive_quantity}">
                                    <input type="hidden" name="stock_ring_balance[]" class="stock-ring_balance" value="${displayStockRingBalance}">
                                </tr>`;
                            $('#exchange-product-table tbody').append(row);
                        });

                        $('select[name="sell_list_filter_contact_id"]').val(response.contact_id).trigger('change');
                        isChecked = true;
                        $('#submit-button').prop('disabled', false);
                    } else {
                        $('#submit-button').prop('disabled', true);
                        isChecked = false;
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 400) {
                        alert('This order is already existing with customer reward.');
                    } else if (xhr.status === 404) {
                        alert('Transaction not found.');
                    } else {
                        alert('An error occurred. Please try again.');
                    }
                    $('#submit-button').prop('disabled', true);
                    isChecked = false;
                }
            });
        });
    });
</script>
@endsection