@extends('layouts.app')

@section('title', __('Edit Supplier Reward Exchange'))

@section('content')
<section class="content-header">
    <h1>{{ __('Edit Supplier Reward Exchange') }}</h1>
</section>
<style>
    input[type=number] {
        -moz-appearance: textfield; /* Firefox */
    }
    
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none; /* Chrome, Safari, Edge */
        margin: 0;
    }

    th, td {
        padding: 8px;
        font-size: 15px;
    }

    th {
        font-weight: bold;
    }

    #exchange-product-table th {
        background-color: #28a745;
        color: white;
    }

    #exchange-product-table input[type='number'] {
        width: 50px;
        text-align: center;
    }

    .form-control:focus {
        box-shadow: none;
    }

    .quantity-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 3px;
    }

    .search-bar {
        margin-bottom: 15px;
    }

    .small-icon {
        font-size: 12px;
    }

    #exchange-product-table .fa-times {
        font-size: 12px;
    }

    .search-results {
        box-shadow: 0 4px 6px rgba(0,0,0,.1);
        max-height: 200px;
        overflow-y: auto;
    }

    .search-item {
        padding: 8px 10px;
        cursor: pointer;
        line-height: 20px;
    }

    .search-item:hover {
        background-color: #f0f0f0;
    }

    .set-btn {
        width: 25px;
        height: 25px;
        font-size: 15px;
        line-height: 1;
        padding: 0;
        border: none;
    }

    input.set-quantity {
        width: 50px;
        text-align: center;
        border: none;
        outline: none;
        font-size: 15px;
    }

    .btn-danger {
        background-color: #d9534f;
        color: white;
    }

    .btn-success {
        background-color: #5cb85c;
        color: white;
    }
</style>
{!! Form::open(['url' => route('sale-reward-supplier.update', $transaction->id), 'method' => 'PUT', 'id' => 'edit-supplier-reward-form']) !!}
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
                                {!! Form::select('select_location_id', $business_locations, $transaction->location_id, ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required', 'autofocus', in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '']) !!}
                                @if(in_array($transaction->status, ['completed', 'partial']))
                                    {!! Form::hidden('select_location_id', $transaction->location_id) !!}
                                @endif
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
                                        {!! Form::label('sell_list_filter_contact_id', __('Supplier') . ':') !!}
                                        {!! Form::select('sell_list_filter_contact_id', $contact, $transaction->contact_id, ['class' => 'form-control select2', 'style' => 'width:100%', in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '']) !!}
                                        @if(in_array($transaction->status, ['completed', 'partial']))
                                            {!! Form::hidden('sell_list_filter_contact_id', $transaction->contact_id) !!}
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('invoice_no', __('Reference No') . ':') !!}
                                        {!! Form::text('invoice_no', $transaction->ref_no, ['class' => 'form-control', 'placeholder' => __('Enter Reference Number'), in_array($transaction->status, ['completed', 'partial']) ? 'readonly' : '']) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('transaction_date', __('Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', $transaction->transaction_date, ['class' => 'form-control', 'id' => 'transaction_date', 'readonly', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('sub_type', __('Ring Status') . ':') !!}
                                        {!! Form::select('sub_type', ['pending' => 'Pending', 'send' => 'Send'], $transaction->sub_type, ['class' => 'form-control select2', 'required' => 'required']) !!}
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
                                <!-- Search Input -->
                                <div class="col-md-2"></div>
                                <div class="col-md-8"> 
                                    <div class="search-bar" style="position: relative;">
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                            <input type="text" id="productSearch" class="form-control" placeholder="{{ __('Enter Product name / SKU / Scan bar code') }}" {{ in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '' }}>
                                        </div>
                                        <div id="search-results" style="position: absolute; width: 100%; background: white; z-index: 1000; border: 1px solid #ccc; display: none;"></div>
                                    </div>
                                </div>
                                <div class="col-md-2"></div>

                                <table class="table table-bordered" id="exchange-product-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('Product Name') }}</th>
                                            <th>{{ __('Exchange Quantity') }}</th>
                                            <th>{{ __('Receive Quantity') }}</th>
                                            <th>{{ __('Quantity') }}</th>
                                            <th>{{ __('Price') }}</th>
                                            <th>{{ __('Total') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sell_lines as $index => $line)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $line->product->name }}
                                                {!! Form::hidden("products[$index][product_id]", $line->product_id) !!}
                                                {!! Form::hidden("products[$index][exchange_quantity]", $line->exchange_quantity) !!}
                                            </td>
                                            <td>{{ $line->exchange_quantity }}</td>
                                            <td>{{ $line->receive_quantity }}</td>
                                            <td class="quantity-container">
                                                <button type="button" class="btn btn-danger set-btn" onclick="adjustQuantity(this, -1)" {{ in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '' }}>-</button>
                                                <input type="number" class="set-quantity" name="products[{{ $index }}][quantity]" value="{{ $line->quantity }}" min="1" {{ in_array($transaction->status, ['completed', 'partial']) ? 'readonly' : '' }} />
                                                <button type="button" class="btn btn-success set-btn" onclick="adjustQuantity(this, 1)" {{ in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '' }}>+</button>
                                            </td>
                                            <td>
                                                {!! Form::hidden("products[$index][price]", $line->unit_price) !!}
                                                ${{ number_format($line->unit_price, 2) }}
                                            </td>
                                            <td class="total">${{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-danger fa fa-times" onclick="removeRow(this)" {{ in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '' }}></button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('note', __('Note') . ':') !!}
                                    {!! Form::textarea('note', $transaction->additional_notes, ['class' => 'form-control', 'rows' => 3, in_array($transaction->status, ['completed', 'partial']) ? 'disabled' : '']) !!}
                                    @if(in_array($transaction->status, ['completed', 'partial']))
                                        {!! Form::hidden('note', $transaction->additional_notes) !!}
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if(in_array($transaction->status, ['completed', 'partial']))
                        <div class="row">
                            <div class="col-md-12">
                                <p>{{ __('This transaction is completed or partial. Only Ring Status can be updated.') }}</p>
                            </div>
                        </div>
                        @endif
                        <div class="form-group">
                            {!! Form::submit(__('Update'), ['class' => 'btn btn-success']) !!}
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
    $(function () {
        @if($transaction->status === 'pending')
        $('#transaction_date').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            locale: { format: 'YYYY-MM-DD HH:mm:ss' }
        });
        @endif
    });

    function adjustQuantity(button, change) {
        @if($transaction->status === 'pending')
        let $input = $(button).siblings('.set-quantity');
        let currentValue = parseInt($input.val() || 0, 10); // Ensure valid integer input
        let newValue = Math.max(1, currentValue + change); // Prevent value below 1
        $input.val(newValue).trigger('change'); // Trigger 'change' to update totals
        @endif
    }

    function updateRow(input) {
        @if($transaction->status === 'pending')
        let $row = $(input).closest('tr');
        let quantity = parseFloat($(input).val() || 0); // Get valid quantity or default to 0
        let price = parseFloat($row.find('.price-hidden').val() || 0); // Get price from hidden input
        let total = (quantity * price).toFixed(2); // Calculate total

        // Update the total column
        $row.find('.total').text('$' + (isNaN(total) ? '0.00' : total));
        @endif
    }

    @if($transaction->status === 'pending')
    function addProductToTable(product) {
        var $tableBody = $('#exchange-product-table tbody');
        var rowCount = $tableBody.children().length;

        var quantity = 1; // Default quantity for new product
        var exchangeQuantity = product.exchange_quantity || 1; // Default exchange quantity if not provided
        var price = parseFloat(product.price).toFixed(2); // Ensure price is a valid decimal number
        var total = (quantity * price).toFixed(2); // Calculate total for this product

        // Generate the HTML for the new table row
        var newRow = `
            <tr>
                <td>${rowCount + 1}</td>
                <td>
                    ${product.name} (${product.sku})
                    <input type="hidden" name="products[${rowCount}][product_id]" value="${product.id}">
                    <input type="hidden" name="products[${rowCount}][exchange_quantity]" value="${exchangeQuantity}">
                </td>
                <td>${exchangeQuantity}</td>
                <td>${product.receive_quantity || 0}</td>
                <td class="quantity-container">
                    <button type="button" class="btn btn-danger set-btn" onclick="adjustQuantity(this, -1)">-</button>
                    <input type="number" class="set-quantity" name="products[${rowCount}][quantity]" value="${quantity}" min="1" oninput="updateRow(this)" />
                    <button type="button" class="btn btn-success set-btn" onclick="adjustQuantity(this, 1)">+</button>
                </td>
                <td>
                    <input type="hidden" class="price-hidden" name="products[${rowCount}][price]" value="${price}">
                    $${price}
                </td>
                <td class="total">$${total}</td>
                <td>
                    <button type="button" class="btn btn-danger fa fa-times" onclick="removeRow(this)"></button>
                </td>
            </tr>`;

        $tableBody.append(newRow); // Add the new row to the table
        updateRowNumbers(); // Update row numbering
    }

    function removeRow(button) {
        $(button).closest('tr').remove();
        updateRowNumbers();
    }

    function updateRowNumbers() {
        $('#exchange-product-table tbody tr').each((index, row) => {
            $(row).find('td:first').text(index + 1);
        });
    }

    // Search product logic
    $('#productSearch').on('input', function () {
        let term = $(this).val();
        if (term.length) {
            $.get('{{ route('sale-reward-supplier.searchProduct') }}', { term }, displaySearchResults);
        } else {
            $('#search-results').hide();
        }
    });

    function displaySearchResults(products) {
        let $results = $('#search-results').empty().show();
        if (products.length) {
            products.forEach(p => {
                $results.append(`<div class="search-item" data-id="${p.product.id}">${p.product.name} (${p.product.sku})</div>`);
            });
        } else {
            $results.html('<div class="search-item">No products found.</div>');
        }
    }

    $('#search-results').on('click', '.search-item', function () {
        let id = $(this).data('id');
        $.get('{{ route('sale-reward-supplier.getProduct') }}', { id }, addProductToTable);
        $('#search-results').hide();
    });
    @endif
</script>
@endsection