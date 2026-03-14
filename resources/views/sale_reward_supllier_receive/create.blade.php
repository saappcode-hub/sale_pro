@extends('layouts.app')

@section('title', __('Add Reward Stock Received'))

@section('content')
<section class="content-header">
    <h1>{{ __('Add Reward Stock Received') }}</h1>
</section>
<style>
    input[type=number] {
        -moz-appearance: textfield;
    }
    
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
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

    /* Delete button styling - Make it bigger like supplier exchange */
    .delete-product-btn {
        width: 35px;
        height: 35px;
        padding: 0;
        border: none;
        background-color: #d9534f;
        color: white;
        cursor: pointer;
        border-radius: 3px;
        font-size: 18px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }

    .delete-product-btn:hover {
        background-color: #c9302c;
    }

    .delete-product-btn .fa-times {
        font-size: 20px;
    }
</style>

{!! Form::open(['url' => route('sale-reward-supplier-receive.store'), 'method' => 'POST', 'id' => 'supplier-reward-form']) !!}
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
                                        {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                                        {!! Form::label('transaction_date', __('Date') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-calendar"></i>
                                            </span>
                                            {!! Form::text('transaction_date', null, ['class' => 'form-control', 'id' => 'transaction_date', 'readonly', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        {!! Form::label('status', __('Status') . ':') !!}
                                        {!! Form::select('status', ['pending' => __('Pending'), 'completed' => __('Completed')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'required']) !!}
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
                                            <input type="text" id="productSearch" class="form-control" placeholder="{{ __('Enter Product name / SKU / Scan bar code') }}">
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
                                        <!-- Product rows will be added dynamically by JavaScript -->
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

    var $searchInput = $('#productSearch');
    var $resultsDiv = $('#search-results');
    var $tableBody = $('#exchange-product-table tbody');

    // Function to fetch product details and add to table
    function fetchAndAddProduct(productId) {
        $.ajax({
            url: "{{ route('sale-reward-supplier-receive.getProduct') }}",
            method: 'GET',
            data: { id: productId },
            success: function(response) {
                if (response.name) {
                    addProductToTable(response);
                    clearSearchInput();
                } else {
                    alert('Detailed product info not found.');
                }
            },
            error: function(xhr) {
                console.log('Error:', xhr);
                alert('Error fetching detailed product info.');
            }
        });
    }

    $searchInput.on('input', function() {
        var searchTerm = $(this).val();
        if (searchTerm.length > 0) {
            $.ajax({
                url: "{{ route('sale-reward-supplier-receive.searchProduct') }}",
                method: 'GET',
                data: { term: searchTerm },
                success: function(products) {
                    if (products.length > 0) {
                        displaySearchResults(products);
                        // Check for an exact match and auto-select it
                        var exactMatch = products.find(p => p.product.name.toLowerCase() === searchTerm.toLowerCase());
                        if (exactMatch) {
                            fetchAndAddProduct(exactMatch.product.id); // Fetch full details for exact match
                            $resultsDiv.hide();
                            clearSearchInput(); // Clear the search input after auto-selecting a product
                        } else {
                            $resultsDiv.show();
                        }
                    } else {
                        $resultsDiv.html('<div class="search-item">No products found.</div>').show();
                    }
                },
                error: function(xhr) {
                    console.log('Search error:', xhr);
                    $resultsDiv.html('<div class="search-item">Error fetching products.</div>').show();
                }
            });
        } else {
            $resultsDiv.hide();
        }
    });

    function addProductToTable(product) {
        // Check if product already exists
        var productExists = false;
        $tableBody.find('input[name*="[product_id]"]').each(function() {
            if (parseInt($(this).val()) === parseInt(product.id)) {
                productExists = true;
                return false;
            }
        });

        if (productExists) {
            alert('This product is already in the table.');
            return;
        }

        var quantity = 1;
        var exchangeQuantity = product.exchange_quantity || 1;
        var receiveQuantity = product.receive_quantity || 1;
        var price = parseFloat(product.price).toFixed(2);
        var total = (quantity * price).toFixed(2);
        var rowCount = $tableBody.children().length;

        // Generate the HTML for the new table row
        var newRow = `
            <tr>
                <td>${rowCount + 1}</td>
                <td>${product.name} (${product.sku})
                    <input type="hidden" name="products[${rowCount}][product_id]" value="${product.id}">
                </td>
                <td>
                    <input type="hidden" class="exchange-quantity" value="${exchangeQuantity}">
                    ${exchangeQuantity}
                </td>
                <td>${receiveQuantity}</td>
                <td class="quantity-container">
                    <button type="button" class="btn btn-danger set-btn" onclick="decrementQuantity(this)">-</button>
                    <input type="number" class="set-quantity" name="products[${rowCount}][quantity]" value="${quantity}" min="1" oninput="updateTotal(this)" />
                    <button type="button" class="btn btn-success set-btn" onclick="incrementQuantity(this)">+</button>
                </td>
                <td class="price">$${price}
                    <input type="hidden" name="products[${rowCount}][price]" value="${price}">
                </td>
                <td class="total">$${total}</td>
                <td><button type="button" class="delete-product-btn" onclick="removeRow(this)" title="Delete"><i class="fa fa-times"></i></button></td>
            </tr>`;
        $tableBody.append(newRow); // Add the new row to the table body
        updateRowNumbers();
    }

    function displaySearchResults(products) {
        $resultsDiv.empty();
        products.forEach(function(product) {
            $resultsDiv.append(`<div class="search-item" data-product-id="${product.product.id}">${product.product.name} (${product.product.sku})</div>`);
        });
    }

    $(document).on('click', '.search-item', function() {
        var productId = $(this).data('product-id');
        fetchAndAddProduct(productId);
        $resultsDiv.hide();
        clearSearchInput();
    });

    $(document).mouseup(function(e) {
        if (!$searchInput.is(e.target) && $resultsDiv.has(e.target).length === 0) {
            $resultsDiv.hide();
        }
    });

    function clearSearchInput() {
        $searchInput.val('');
    }

    // Make functions globally accessible
    window.incrementQuantity = function(button) {
        var input = $(button).siblings('input.set-quantity');
        var currentVal = parseInt(input.val());
        input.val(currentVal + 1);
        updateTotal(input);
    }

    window.decrementQuantity = function(button) {
        var input = $(button).siblings('input.set-quantity');
        var currentVal = parseInt(input.val());
        if (currentVal > 1) {
            input.val(currentVal - 1);
            updateTotal(input);
        }
    }

    window.updateTotal = function(input) {
        var quantity = parseInt($(input).val());
        var price = parseFloat($(input).closest('tr').find('.price').text().replace('$', ''));
        var total = (quantity * price).toFixed(2);
        $(input).closest('tr').find('.total').text('$' + total);
    }

    window.removeRow = function(button) {
        $(button).closest('tr').remove();
        updateRowNumbers();
    }

    function updateRowNumbers() {
        $('#exchange-product-table tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    $('#supplier-reward-form').on('submit', function(e) {
        e.preventDefault();

        if ($(this).data('submitting') === true) {
            return false;
        }

        var status = $('#status').val();
        if (!status) {
            alert('Please select a status.');
            return false;
        }

        if ($('#exchange-product-table tbody tr').length === 0) {
            alert('No products added to the table. Please add at least one product.');
            return false;
        }

        var products = [];
        $('#exchange-product-table tbody tr').each(function() {
            var product_id = $(this).find('input[name*="[product_id]"]').val();
            var quantity = $(this).find('.set-quantity').val();
            var price = parseFloat($(this).find('.price').text().replace('$', ''));

            products.push({
                product_id: product_id,
                quantity: quantity,
                price: price
            });
        });

        if (products.length === 0) {
            alert('No valid products to submit.');
            return false;
        }

        submitFormWithProtection(products);
    });

    function submitFormWithProtection(products) {
        const $form = $('#supplier-reward-form');
        const $submitBtn = $form.find('input[type="submit"]');
        
        $form.data('submitting', true);
        $submitBtn.prop('disabled', true).val('Processing...');
        
        $('<input>').attr({
            type: 'hidden',
            name: 'products',
            value: JSON.stringify(products),
        }).appendTo($form);

        $form[0].submit();
    }
});
</script>
@endsection