@extends('layouts.app')

@section('title', __('Edit Supplier Exchange(Ring Cash)'))

@section('content')
<section class="content-header">
    <h1>{{ __('Edit Supplier Exchange(Ring Cash)') }}</h1>
</section>

<style>
    .quantity-input-group {
        display: flex;
        align-items: center;
        border: 1px solid #ccc;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 5px;
        width: 120px;
    }

    .quantity-input-group .btn {
        border: none;
        outline: none;
        padding: 5px 10px;
        font-size: 16px;
    }

    .quantity-input-group .set-quantity {
        text-align: center;
        border: none;
        outline: none;
        flex: 1;
        padding: 5px;
        width: 40px;
    }

    .quantity-input-group .btn-outline-danger {
        color: #d9534f;
        background-color: #f5f5f5;
    }

    .quantity-input-group .btn-outline-success {
        color: #5cb85c;
        background-color: #f5f5f5;
    }

    .remove-product-btn {
        color: white;
        background-color: red;
        border: none;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
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

    .search-container {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .search-input-group {
        width: 60%;
    }

    .ring-unit-label {
        margin-right: 10px;
        font-weight: normal;
        width: 80px;
    }

    .ring-unit-row {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }

    .total-rings {
        font-weight: bold;
    }

    .cash-ring-amount {
        margin-top: 5px;
        color: #dc3545;
        font-size: 12px;
        font-weight: normal;
    }

    .qty-btn.disabled, input[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .qty-btn.disabled:hover {
        background-color: #f5f5f5;
    }

    .readonly-field {
        background-color: #f9f9f9;
        cursor: not-allowed;
    }
</style>

{!! Form::open(['url' => route('supplier-cash-ring-balance.update', $transaction->id), 'method' => 'PUT', 'id' => 'supplier-ring-balance-edit-form']) !!}
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
                                {!! Form::select('select_location_id', $business_locations, $transaction->location_id, ['class' => 'form-control input-sm readonly-field', 'id' => 'select_location_id', 'required', 'disabled' => true]) !!}
                                {!! Form::hidden('select_location_id', $transaction->location_id) !!}
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
                                        {!! Form::select('sell_list_filter_contact_id', $contacts, $transaction->supplier_id, ['class' => 'form-control select2 readonly-field', 'style' => 'width:100%', 'disabled' => true]) !!}
                                        {!! Form::hidden('sell_list_filter_contact_id', $transaction->supplier_id) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('invoice_no', __('Reference No') . ':') !!}
                                        {!! Form::text('invoice_no', $transaction->invoice_no, ['class' => 'form-control', 'placeholder' => __('Enter Reference Number')]) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('transaction_date', __('Transaction Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', $transaction->transaction_date, ['class' => 'form-control', 'id' => 'transaction_date', 'readonly', 'required']) !!}
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
                                        {!! Form::select('status', ['pending' => __('Pending'), 'send' => __('Send')], $transaction->status, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'status']) !!}
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
                                <div class="search-container">
                                    <div class="input-group search-input-group">
                                        {!! Form::text('search_product', null, ['class' => 'form-control', 'id' => 'search_product', 'placeholder' => __('Enter Product Name / SKU / Scan bar code')]) !!}
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-info" id="search-product-btn">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                <div id="product-suggestions" class="list-group" style="display: none; position: absolute; z-index: 1000; width: 60%; margin-left: 20%;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table table-bordered" id="exchange-product-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Additional Rewards & Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('note', __('Note') . ':') !!}
                                    {!! Form::textarea('note', $transaction->note, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter any additional notes (optional)')]) !!}
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            {!! Form::submit(__('Update'), ['class' => 'btn btn-primary']) !!}
                            <a href="{{ route('supplier-cash-ring-balance.index') }}" class="btn btn-default">{{ __('Back') }}</a>
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
        
        $('#transaction_date').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            locale: { format: 'YYYY-MM-DD HH:mm:ss' },
            startDate: moment('{{ $transaction->transaction_date }}'),
            autoUpdateInput: true
        });

        $('#transaction_date').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm:ss'));
        });

        $('#sell_list_filter_contact_id').select2({
            templateResult: formatContact,
            templateSelection: formatContactSelection,
            width: '100%',
            disabled: true
        });

        function formatContact(contact) {
            if (!contact.id) return contact.text;
            return $('<div>' + contact.text.split('<br>').join('</div><div>') + '</div>');
        }

        function formatContactSelection(contact) {
            if (!contact.id) return contact.text;
            return contact.text.split(' (')[0].trim();
        }

        loadExistingProducts();

        function loadExistingProducts() {
            var existingProducts = @json($transactionDetails);
            
            existingProducts.forEach(function(detail) {
                addExistingProductToTable(detail);
            });
        }

        function addExistingProductToTable(detail) {
            var uniqueId = detail.product_id + '_cash';
            var productName = detail.product.name;
            var displayName = productName + ' (Cash Ring)';
            
            var cashRingValues = { dollar: [], riel: [] };
            
            var productDetails = @json($transactionDetails).filter(function(d) {
                return d.product_id === detail.product_id;
            });
            
            productDetails.forEach(function(pd) {
                var currencyType = pd.cash_ring_balance.type_currency == 1 ? 'dollar' : 'riel';
                var currencySymbol = pd.cash_ring_balance.type_currency == 1 ? '$' : '៛';
                
                var actualStock = @json($stockData)[detail.product_id + '_' + pd.cash_ring_balance_id] || 0;
                
                cashRingValues[currencyType].push({
                    id: pd.cash_ring_balance_id,
                    unit_value: pd.cash_ring_balance.unit_value,
                    currency_symbol: currencySymbol,
                    redemption_value: pd.cash_ring_balance.redemption_value,
                    stock_cash_ring_balance: actualStock,
                    current_quantity: pd.quantity
                });
            });

            if ($(`#exchange-product-table tr[data-unique-id="${uniqueId}"]`).length > 0) {
                return;
            }

            var quantityInputs = '';
            quantityInputs += `<input type="hidden" name="products[${uniqueId}][product_id]" value="${detail.product_id}">`;
            
            var dollarSection = '';
            var rielSection = '';
            
            if (cashRingValues.dollar && cashRingValues.dollar.length > 0) {
                dollarSection += '<div style="display: inline-block; vertical-align: top; margin-right: 50px;">';
                dollarSection += '<div style="font-weight: bold; margin-bottom: 10px;">Dollar ($)</div>';
                
                cashRingValues.dollar.forEach(function(cashRing) {
                    var actualStock = cashRing.stock_cash_ring_balance || 0;
                    var currentQty = cashRing.current_quantity || 0;
                    var isDisabled = actualStock <= 0;
                    var disabledClass = isDisabled ? 'disabled' : '';
                    var disabledAttr = isDisabled ? 'disabled' : '';
                    
                    dollarSection += `
                        <div style="display: flex; align-items: center; margin-bottom: 8px; ${isDisabled ? 'opacity: 0.5;' : ''}">
                            <span style="min-width: 60px; margin-right: 10px;">${cashRing.unit_value}${cashRing.currency_symbol} :</span>
                            <div class="input-group quantity-input-group">
                                <button type="button" class="btn btn-outline-danger qty-btn ${disabledClass}" data-action="decrease" ${disabledAttr}>-</button>
                                <input type="number" class="form-control set-quantity" 
                                    name="products[${uniqueId}][cash_quantities][${cashRing.id}]" 
                                    min="0" max="${actualStock}" value="${currentQty}" 
                                    data-cash-ring-id="${cashRing.id}"
                                    data-unit-value="${cashRing.unit_value}" 
                                    data-redemption-value="${cashRing.redemption_value}"
                                    data-currency="dollar"
                                    data-stock="${actualStock}"
                                    ${disabledAttr}>
                                <button type="button" class="btn btn-outline-success qty-btn ${disabledClass}" data-action="increase" ${disabledAttr}>+</button>
                            </div>
                            <span style="margin-left: 10px; font-size: 12px; color: ${isDisabled ? '#dc3545' : '#6c757d'};">
                                Stock: ${actualStock}
                            </span>
                        </div>`;
                });
                
                dollarSection += `
                    <div style="margin-top: 10px; font-weight: bold;">
                        Total: <span class="total-currency-value" data-currency="dollar" data-unique-id="${uniqueId}">0</span>$
                    </div>`;
                dollarSection += '</div>';
            }

            if (cashRingValues.riel && cashRingValues.riel.length > 0) {
                rielSection += '<div style="display: inline-block; vertical-align: top;">';
                rielSection += '<div style="font-weight: bold; margin-bottom: 10px;">Riel (៛)</div>';
                
                cashRingValues.riel.forEach(function(cashRing) {
                    var actualStock = cashRing.stock_cash_ring_balance || 0;
                    var currentQty = cashRing.current_quantity || 0;
                    var isDisabled = actualStock <= 0;
                    var disabledClass = isDisabled ? 'disabled' : '';
                    var disabledAttr = isDisabled ? 'disabled' : '';
                    
                    rielSection += `
                        <div style="display: flex; align-items: center; margin-bottom: 8px; ${isDisabled ? 'opacity: 0.5;' : ''}">
                            <span style="min-width: 80px; margin-right: 10px;">${cashRing.unit_value}${cashRing.currency_symbol} :</span>
                            <div class="input-group quantity-input-group">
                                <button type="button" class="btn btn-outline-danger qty-btn ${disabledClass}" data-action="decrease" ${disabledAttr}>-</button>
                                <input type="number" class="form-control set-quantity" 
                                    name="products[${uniqueId}][cash_quantities][${cashRing.id}]" 
                                    min="0" max="${actualStock}" value="${currentQty}" 
                                    data-cash-ring-id="${cashRing.id}"
                                    data-unit-value="${cashRing.unit_value}" 
                                    data-redemption-value="${cashRing.redemption_value}"
                                    data-currency="riel"
                                    data-stock="${actualStock}"
                                    ${disabledAttr}>
                                <button type="button" class="btn btn-outline-success qty-btn ${disabledClass}" data-action="increase" ${disabledAttr}>+</button>
                            </div>
                            <span style="margin-left: 10px; font-size: 12px; color: ${isDisabled ? '#dc3545' : '#6c757d'};">
                                Stock: ${actualStock}
                            </span>
                        </div>`;
                });
                
                rielSection += `
                    <div style="margin-top: 10px; font-weight: bold;">
                        Total: <span class="total-currency-value" data-currency="riel" data-unique-id="${uniqueId}">0</span>៛
                    </div>`;
                rielSection += '</div>';
            }

            quantityInputs += dollarSection + rielSection;

            $('#exchange-product-table tbody').append(`
                <tr data-unique-id="${uniqueId}" data-product-id="${detail.product_id}" data-type="cash">
                    <td>${displayName}</td>
                    <td>${quantityInputs}</td>
                    <td style="text-align: center; vertical-align: middle;">
                        <button type="button" class="remove-product-btn">Delete</button>
                    </td>
                </tr>
            `);

            updateCashRingTotals(uniqueId);
        }

        $('#search_product').on('keyup', function() {
            let searchTerm = $(this).val();
            let locationId = $('#select_location_id').val();
            
            if (!locationId) {
                alert('Please select a location first.');
                return;
            }
            
            if (searchTerm.length >= 2) {
                $.ajax({
                    url: '{{ url("supplier-cash-ring-balance/search-product") }}',
                    method: 'GET',
                    data: { 
                        query: searchTerm,
                        location_id: locationId,
                        transaction_id: {{ $transaction->id }}
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        $('#product-suggestions').empty().show();
                        response.products.forEach(function(product) {
                            $('#product-suggestions').append(`
                                <a href="#" class="list-group-item list-group-item-action" 
                                   data-id="${product.id}" 
                                   data-product-id="${product.product_id}"
                                   data-name="${product.name}" 
                                   data-sku="${product.sku}"
                                   data-type="${product.type}"
                                   data-display-name="${product.display_name}"
                                   data-cash-ring-values='${JSON.stringify(product.cash_ring_values || {})}'>
                                    <strong>${product.display_name}</strong> - ${product.sku}
                                </a>
                            `);
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error, xhr.responseText);
                        $('#product-suggestions').hide();
                    }
                });
            } else {
                $('#product-suggestions').hide();
            }
        });

        $(document).on('click', '#product-suggestions .list-group-item', function(e) {
            e.preventDefault();
            let productData = $(this).data();
            let uniqueId = productData.id;
            let productId = productData.productId;
            let displayName = productData.displayName;
            let cashRingValues = productData.cashRingValues || { dollar: [], riel: [] };

            if ($(`#exchange-product-table tr[data-unique-id="${uniqueId}"]`).length > 0) {
                alert('This product option is already added.');
                return;
            }

            addNewProductToTable(uniqueId, productId, displayName, cashRingValues);

            $('#product-suggestions').hide();
            $('#search_product').val('');
        });

        function addNewProductToTable(uniqueId, productId, displayName, cashRingValues) {
            let quantityInputs = '';
            quantityInputs += `<input type="hidden" name="products[${uniqueId}][product_id]" value="${productId}">`;
            
            let dollarSection = '';
            let rielSection = '';
            
            if (cashRingValues.dollar && cashRingValues.dollar.length > 0) {
                dollarSection += '<div style="display: inline-block; vertical-align: top; margin-right: 50px;">';
                dollarSection += '<div style="font-weight: bold; margin-bottom: 10px;">Dollar ($)</div>';
                
                cashRingValues.dollar.forEach(function(cashRing) {
                    let stock = cashRing.stock_cash_ring_balance || 0;
                    let isDisabled = stock <= 0;
                    let disabledClass = isDisabled ? 'disabled' : '';
                    let disabledAttr = isDisabled ? 'disabled' : '';
                    
                    dollarSection += `
                        <div style="display: flex; align-items: center; margin-bottom: 8px; ${isDisabled ? 'opacity: 0.5;' : ''}">
                            <span style="min-width: 60px; margin-right: 10px;">${cashRing.unit_value}${cashRing.currency_symbol} :</span>
                            <div class="input-group quantity-input-group">
                                <button type="button" class="btn btn-outline-danger qty-btn ${disabledClass}" data-action="decrease" ${disabledAttr}>-</button>
                                <input type="number" class="form-control set-quantity" 
                                    name="products[${uniqueId}][cash_quantities][${cashRing.id}]" 
                                    min="0" max="${stock}" value="0" 
                                    data-cash-ring-id="${cashRing.id}"
                                    data-unit-value="${cashRing.unit_value}" 
                                    data-redemption-value="${cashRing.redemption_value}"
                                    data-currency="dollar"
                                    data-stock="${stock}"
                                    ${disabledAttr}>
                                <button type="button" class="btn btn-outline-success qty-btn ${disabledClass}" data-action="increase" ${disabledAttr}>+</button>
                            </div>
                            <span style="margin-left: 10px; font-size: 12px; color: ${isDisabled ? '#dc3545' : '#6c757d'};">
                                Stock: ${stock}
                            </span>
                        </div>`;
                });
                
                dollarSection += `
                    <div style="margin-top: 10px; font-weight: bold;">
                        Total: <span class="total-currency-value" data-currency="dollar" data-unique-id="${uniqueId}">0</span>$
                    </div>`;
                dollarSection += '</div>';
            }

            if (cashRingValues.riel && cashRingValues.riel.length > 0) {
                rielSection += '<div style="display: inline-block; vertical-align: top;">';
                rielSection += '<div style="font-weight: bold; margin-bottom: 10px;">Riel (៛)</div>';
                
                cashRingValues.riel.forEach(function(cashRing) {
                    let stock = cashRing.stock_cash_ring_balance || 0;
                    let isDisabled = stock <= 0;
                    let disabledClass = isDisabled ? 'disabled' : '';
                    let disabledAttr = isDisabled ? 'disabled' : '';
                    
                    rielSection += `
                        <div style="display: flex; align-items: center; margin-bottom: 8px; ${isDisabled ? 'opacity: 0.5;' : ''}">
                            <span style="min-width: 80px; margin-right: 10px;">${cashRing.unit_value}${cashRing.currency_symbol} :</span>
                            <div class="input-group quantity-input-group">
                                <button type="button" class="btn btn-outline-danger qty-btn ${disabledClass}" data-action="decrease" ${disabledAttr}>-</button>
                                <input type="number" class="form-control set-quantity" 
                                    name="products[${uniqueId}][cash_quantities][${cashRing.id}]" 
                                    min="0" max="${stock}" value="0" 
                                    data-cash-ring-id="${cashRing.id}"
                                    data-unit-value="${cashRing.unit_value}" 
                                    data-redemption-value="${cashRing.redemption_value}"
                                    data-currency="riel"
                                    data-stock="${stock}"
                                    ${disabledAttr}>
                                <button type="button" class="btn btn-outline-success qty-btn ${disabledClass}" data-action="increase" ${disabledAttr}>+</button>
                            </div>
                            <span style="margin-left: 10px; font-size: 12px; color: ${isDisabled ? '#dc3545' : '#6c757d'};">
                                Stock: ${stock}
                            </span>
                        </div>`;
                });
                
                rielSection += `
                    <div style="margin-top: 10px; font-weight: bold;">
                        Total: <span class="total-currency-value" data-currency="riel" data-unique-id="${uniqueId}">0</span>៛
                    </div>`;
                rielSection += '</div>';
            }

            quantityInputs += dollarSection + rielSection;

            $('#exchange-product-table tbody').append(`
                <tr data-unique-id="${uniqueId}" data-product-id="${productId}" data-type="cash">
                    <td>${displayName}</td>
                    <td>${quantityInputs}</td>
                    <td style="text-align: center; vertical-align: middle;">
                        <button type="button" class="remove-product-btn">Delete</button>
                    </td>
                </tr>
            `);

            updateCashRingTotals(uniqueId);
        }

        $(document).on('click', '.qty-btn', function() {
            if ($(this).hasClass('disabled') || $(this).prop('disabled')) {
                return;
            }
            
            let $input = $(this).siblings('input.set-quantity');
            let currentValue = parseInt($input.val()) || 0;
            let maxStock = parseInt($input.data('stock')) || 0;
            
            if ($(this).data('action') === 'increase') {
                if (currentValue < maxStock) {
                    $input.val(currentValue + 1);
                }
            } else if ($(this).data('action') === 'decrease' && currentValue > 0) {
                $input.val(currentValue - 1);
            }
            
            let uniqueId = $(this).closest('tr').data('unique-id');
            updateCashRingTotals(uniqueId);
        });

        $(document).on('input', '.set-quantity', function() {
            if ($(this).prop('disabled')) {
                return;
            }
            
            let $input = $(this);
            let value = parseInt($input.val()) || 0;
            let maxStock = parseInt($input.data('stock')) || 0;
            
            if (value < 0) {
                $input.val(0);
                value = 0;
            } else if (value > maxStock) {
                $input.val(maxStock);
                value = maxStock;
                alert(`Maximum available stock is ${maxStock}`);
            }
            
            let uniqueId = $(this).closest('tr').data('unique-id');
            updateCashRingTotals(uniqueId);
        });

        function updateCashRingTotals(uniqueId) {
            let dollarTotal = 0;
            $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="dollar"]`).each(function() {
                let quantity = parseInt($(this).val()) || 0;
                let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
                dollarTotal += quantity * redemptionValue;
            });
            $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="dollar"]`).text(dollarTotal);
            
            let rielTotal = 0;
            $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="riel"]`).each(function() {
                let quantity = parseInt($(this).val()) || 0;
                let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
                rielTotal += quantity * redemptionValue;
            });
            $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="riel"]`).text(rielTotal);
        }

        $(document).on('click', '.remove-product-btn', function() {
            $(this).closest('tr').remove();
        });

        $('#supplier-ring-balance-edit-form').submit(function(e) {
            e.preventDefault();
            
            // Check if form is already being processed
            if ($(this).data('submitting') === true) {
                return false;
            }
            
            let status = $('#status').val();
            if (status !== 'pending' && status !== 'send') {
                alert('Please select a valid status (Pending or Send).');
                $('#status').focus();
                return false;
            }

            let supplierId = $('#sell_list_filter_contact_id').val();
            if (!supplierId || supplierId === '' || supplierId === 'all') {
                alert('Please select a supplier.');
                $('#sell_list_filter_contact_id').focus();
                return false;
            }

            if ($('#exchange-product-table tbody tr').length === 0) {
                alert('Please add at least one product.');
                return false;
            }

            let hasQuantity = false;
            $('.set-quantity').each(function() {
                if (parseInt($(this).val()) > 0) {
                    hasQuantity = true;
                    return false;
                }
            });
            if (!hasQuantity) {
                alert('Please set at least one quantity greater than 0.');
                return false;
            }
            
            console.log('Form data being submitted:');
            let formData = new FormData(this);
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // Mark form as being submitted
            $(this).data('submitting', true);
            
            // Disable submit button and change text
            var $submitBtn = $(this).find('input[type="submit"]');
            var originalText = $submitBtn.val();
            $submitBtn.prop('disabled', true).val('Processing...');
            
            // Submit the form
            var self = this;
            setTimeout(function() {
                self.submit();
            }, 100);
        });
    });
</script>
@endsection