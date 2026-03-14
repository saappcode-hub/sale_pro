@extends('layouts.app')

@section('title', __('Edit Rewards Exchange'))

@section('content')
<section class="content-header">
    <h1>{{ __('Edit Rewards Exchange') }}</h1>
</section>
<style>
    /* Custom styles */
    .set-quantity {
        width: 80px;
        text-align: center;
        padding: 5px;
        font-size: 16px;
    }

    .set-btn {
        width: 30px;
        height: 30px;
        font-size: 20px;
    }

    input[type=number] {
        -moz-appearance: textfield;
    }
    
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    th, td {
        padding: 12px;
        font-size: 16px;
    }

    th {
        font-weight: bold;
    }
    .stock-ring-balance {
        width: 100px;
        text-align: center;
        font-size: 16px;
        padding: 5px;
    }
    
    .field-readonly {
        background-color: #f5f5f5;
        color: #666;
    }
</style>
{!! Form::model($transaction, ['url' => route('sales_reward.update', $transaction->id), 'method' => 'PUT', 'id' => 'reward-form']) !!}
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
                                {!! Form::select('select_location_id', $business_locations, $transaction->location_id, ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required', 'autofocus', 'readonly' => $transaction->status === 'completed']) !!}
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
                                        {!! Form::select('sell_list_filter_contact_id', $contact, $transaction->contact_id, [
                                            'class' => 'form-control select2 field-readonly', 
                                            'style' => 'width:100%',
                                            'disabled' => true
                                        ]) !!}
                                        {!! Form::hidden('sell_list_filter_contact_id', $transaction->contact_id) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('sale_order', __('Sale Order') . ':') !!}
                                        {!! Form::text('sale_order', $transaction->ref_sale_invoice, ['class' => 'form-control field-readonly', 'readonly']) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('transaction_date', __('Transaction Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', $transaction->transaction_date, ['class' => 'form-control', 'id' => 'transaction_date', 'required']) !!}
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
                                        @if($transaction->status === 'completed')
                                            {!! Form::select('status', ['pending' => __('Pending'), 'completed' => __('Completed')], $transaction->status, [
                                                'class' => 'form-control select2 field-readonly', 
                                                'style' => 'width:100%', 
                                                'id' => 'status',
                                                'disabled' => true
                                            ]) !!}
                                            {!! Form::hidden('status', $transaction->status) !!}
                                        @else
                                            {!! Form::select('status', ['pending' => __('Pending'), 'completed' => __('Completed')], $transaction->status, [
                                                'class' => 'form-control select2', 
                                                'style' => 'width:100%', 
                                                'id' => 'status'
                                            ]) !!}
                                        @endif
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
                                        <!-- This section will be dynamically populated using JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('note', __('Note') . ':') !!}
                                    {!! Form::textarea('note', $transaction->additional_notes, [
                                        'class' => 'form-control field-readonly', 
                                        'rows' => 3, 
                                        'placeholder' => __('Enter Note'),
                                        'readonly' => true
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            {!! Form::submit(__('Update'), ['class' => 'btn btn-primary']) !!}
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
        var transactionStatus = '{{ $transaction->status }}';
        
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

            // Recalculate stock ring balance after changing set quantity
            const row = inputField.closest('tr');
            const exchangeQuantity = parseInt(row.find('input[name="exchange_quantity[]"]').val(), 10) || 0;
            const setQuantity = parseInt(inputField.val(), 10) || 0;

            // Update the stockRingBalance display
            row.find('.stock-ring-balance').val(exchangeQuantity * setQuantity);
        });

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

        $('input[name="sale_order"]').prop('readonly', true);

        // Auto check and fetch products based on sale_order when the page loads
        var saleOrder = $('input[name="sale_order"]').val();
        fetchProductsForSaleOrder(saleOrder);

        function fetchProductsForSaleOrder(saleOrder) {
            $.ajax({
                url: '{{ route("sales_reward.check_transaction") }}',
                type: 'GET',
                data: {
                    saleorder: saleOrder,
                    context: 'edit'
                },
                success: function (response) {
                    const { rewards, sell_lines } = response;
                    // Clear previous rows
                    $('#exchange-product-table tbody').empty();

                    // Populate the table with rewards
                    response.rewards.forEach(function(reward, index) {
                        const stockRingBalance = sell_lines[index]?.used_ring_balance || 0;
                        var stockRingBalanceMain = reward.stock_ring_balance || 0; 
                        
                        // Always disable buttons and make quantity readonly for edit (both pending and completed)
                        var isDisabled = true;
                        var disabledAttr = 'disabled';
                        var readonlyClass = 'field-readonly';
                        
                        var row = `
                            <tr style="background-color: whitesmoke;">
                                <td>${reward.exchange_product}</td>
                                <td>${reward.exchange_quantity}</td>
                                <td>$${reward.amount}</td>
                                <td>${reward.receive_quantity}</td>
                                <td class="stock-ring-balance-text">${stockRingBalance} Ring</td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <button type="button" class="btn btn-danger btn-xs set-btn" data-index="${index}" data-action="decrement" ${disabledAttr}>-</button>
                                        <input type="number" class="form-control set-quantity ${readonlyClass}" name="set_quantity[]" value="${response.set_quantity[index]}" min="0" readonly>
                                        <input type="hidden" name="set_quantity[]" value="${response.set_quantity[index]}">
                                        <button type="button" class="btn btn-success btn-xs set-btn" data-index="${index}" data-action="increment" ${disabledAttr}>+</button>
                                    </div>
                                </td>
                                <input type="hidden" name="product_for_sale_id[]" value="${reward.product_for_sale_id}">
                                <input type="hidden" name="exchange_product_id[]" value="${reward.exchange_product_id}">
                                <input type="hidden" name="receive_product_id[]" value="${reward.receive_product_id}">
                                <input type="hidden" name="variation_id[]" value="${reward.variation_id}">
                                <input type="hidden" name="amount[]" value="${reward.amount}">
                                <input type="hidden" name="exchange_quantity[]" value="${reward.exchange_quantity}">
                                <input type="hidden" name="receive_quantity[]" value="${reward.receive_quantity}">
                                <input type="hidden" name="stock_ring_balance[]" class="stock-ring-balance" value="${stockRingBalance}">
                            </tr>
                        `;
                        $('#exchange-product-table tbody').append(row);
                    });
                },
                error: function(xhr) {
                    if (xhr.status === 404) {
                        alert('Transaction not found.');
                    } else {
                        alert('An error occurred. Please try again.');
                    }
                }
            });
        }

        // Handle form submit validation
        $('#reward-form').on('submit', function(e) {
            // Ensure that all fields are properly filled
            var status = $('#status').val();
            if (!status) {
                alert('Please select a valid status before submitting.');
                e.preventDefault();
                return;
            }
        });
    });
</script>
@endsection