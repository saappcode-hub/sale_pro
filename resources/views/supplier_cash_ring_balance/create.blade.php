@extends('layouts.app')

@section('title', __('Supplier Exchange(Ring Cash)'))

@section('content')
<section class="content-header">
    <h1>{{ __('Supplier Exchange(Ring Cash)') }}</h1>
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
</style>

{!! Form::open(['url' => route('supplier-cash-ring-balance.store'), 'method' => 'POST', 'id' => 'supplier-ring-balance-form']) !!}
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
                                        {!! Form::label('sell_list_filter_contact_id', __('Supplier') . ':') !!}
                                        {!! Form::select('sell_list_filter_contact_id', $contacts, $contact_id, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('invoice_no', __('Reference No') . ':') !!}
                                        {!! Form::text('invoice_no', null, ['class' => 'form-control', 'placeholder' => __('Enter Reference Number')]) !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('transaction_date', __('Transaction Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', null, ['class' => 'form-control', 'id' => 'transaction_date', 'readonly', 'required']) !!}
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
                                        {!! Form::select('status', ['all' => __('All'), 'pending' => __('Pending'), 'send' => __('Send')], 'all', ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'status']) !!}
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
                                <!-- Container for displaying the search results dynamically -->
                                <div id="product-suggestions" class="list-group" style="display: none; position: absolute; z-index: 1000; width: 60%; margin-left: 20%;">
                                    <!-- Suggestions will appear here -->
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
                                        <!-- Dynamic rows will be added here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('note', __('Note') . ':') !!}
                                    {!! Form::textarea('note', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter any additional notes (optional)')]) !!}
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            {!! Form::submit(__('Submit'), ['class' => 'btn btn-primary']) !!}
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
        // Date picker initialization
        $('#transaction_date').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            locale: { format: 'YYYY-MM-DD HH:mm:ss' },
            startDate: moment().format('YYYY-MM-DD HH:mm:ss'),
            autoUpdateInput: true
        });

        $('#transaction_date').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm:ss'));
        });

        // Initialize select2 for contact
        $('#sell_list_filter_contact_id').select2({
            templateResult: formatContact,
            templateSelection: formatContactSelection,
            width: '100%'
        });

        function formatContact(contact) {
            if (!contact.id) return contact.text;
            return $('<div>' + contact.text.split('<br>').join('</div><div>') + '</div>');
        }

        function formatContactSelection(contact) {
            if (!contact.id) return contact.text;
            return contact.text.split(' (')[0].trim();
        }

        // Add location change handler to clear products when location changes
        $('#select_location_id').on('change', function() {
            // Clear existing products when location changes
            $('#exchange-product-table tbody').empty();
            $('#product-suggestions').hide();
            $('#search_product').val('');
        });



        // Product search handling
        $('#search_product').on('keyup', function() {
            let searchTerm = $(this).val();
            let locationId = $('#select_location_id').val();
            
            if (!locationId) {
                alert('Please select a location first.');
                $('#select_location_id').focus();
                return;
            }
            
            if (searchTerm.length >= 2) {
                $.ajax({
                    url: '{{ url("supplier-cash-ring-balance/search-product") }}',
                    method: 'GET',
                    data: { 
                        query: searchTerm,
                        location_id: locationId
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

        // Handle product selection (Cash Ring Only)
        $(document).on('click', '#product-suggestions .list-group-item', function(e) {
            e.preventDefault();
            let productData = $(this).data();
            let uniqueId = productData.id;
            let productId = productData.productId;
            let productName = productData.name;
            let productType = productData.type;
            let displayName = productData.displayName;
            let cashRingValues = productData.cashRingValues || { dollar: [], riel: [] };

            // Prevent duplicate products
            if ($(`#exchange-product-table tr[data-unique-id="${uniqueId}"]`).length > 0) {
                alert('This product option is already added.');
                return;
            }

            // Handle Cash Ring only
            if (productType === 'cash') {
                let quantityInputs = '';
                
                // Add hidden field for product_id
                quantityInputs += `<input type="hidden" name="products[${uniqueId}][product_id]" value="${productId}">`;
                
                // Create a single row layout with currencies side by side
                let dollarSection = '';
                let rielSection = '';
                
                // Dollar Section
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

                // Riel Section
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

                // Combine sections
                quantityInputs += dollarSection + rielSection;

                if (!cashRingValues.dollar?.length && !cashRingValues.riel?.length) {
                    quantityInputs += '<div>No cash ring values available.</div>';
                }

                $('#exchange-product-table tbody').append(`
                    <tr data-unique-id="${uniqueId}" data-product-id="${productId}" data-type="${productType}">
                        <td>${displayName}</td>
                        <td>${quantityInputs}</td>
                        <td style="text-align: center; vertical-align: middle;">
                            <button type="button" class="remove-product-btn">Delete</button>
                        </td>
                    </tr>
                `);

                updateCashRingTotals(uniqueId);
            }

            $('#product-suggestions').hide();
            $('#search_product').val('');
        });

        // Quantity adjustment handlers
        $(document).on('click', '.qty-btn', function() {
            // Skip if button is disabled
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

        // Update quantity adjustments via direct input
        $(document).on('input', '.set-quantity', function() {
            // Skip if input is disabled
            if ($(this).prop('disabled')) {
                return;
            }
            
            let $input = $(this);
            let value = parseInt($input.val()) || 0;
            let maxStock = parseInt($input.data('stock')) || 0;
            
            // Validate against stock limits
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
            // Update dollar total
            let dollarTotal = 0;
            $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="dollar"]`).each(function() {
                let quantity = parseInt($(this).val()) || 0;
                let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
                dollarTotal += quantity * redemptionValue;
            });
            $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="dollar"]`).text(dollarTotal);
            
            // Update riel total
            let rielTotal = 0;
            $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="riel"]`).each(function() {
                let quantity = parseInt($(this).val()) || 0;
                let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
                rielTotal += quantity * redemptionValue;
            });
            $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="riel"]`).text(rielTotal);
        }

        // Remove product from table
        $(document).on('click', '.remove-product-btn', function() {
            $(this).closest('tr').remove();
        });

        // Helper function to handle form submission with duplicate prevention
        function submitFormWithProtection() {
            const $form = $('#supplier-ring-balance-form');
            const $submitBtn = $form.find('input[type="submit"]');
            
            // Mark form as being submitted
            $form.data('submitting', true);
            
            // Disable submit button and change text
            $submitBtn.prop('disabled', true).val('Processing...');
            
            // Submit the form
            $form[0].submit();
        }

        // Form submission validation
        $('#supplier-ring-balance-form').submit(function(e) {
            e.preventDefault(); // Always prevent default first
            
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

            // Check if supplier is selected
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
            
            // Debug: Log form data before submission
            console.log('Form data being submitted:');
            let formData = new FormData(this);
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // If validation passes, submit the form with protection
            submitFormWithProtection();
        });
    });
</script>
@endsection