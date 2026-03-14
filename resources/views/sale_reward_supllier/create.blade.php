@extends('layouts.app')

@section('title', __('Supplier Rewards Exchange'))

@section('content')
<section class="content-header">
    <h1>{{ __('Supplier Rewards Exchange') }}</h1>
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
        padding: 12px;
        font-size: 16px;
    }

    th {
        font-weight: bold;
    }

    /* Table styling */
    #exchange-product-table th {
        background-color: #28a745;
        color: white;
    }

    #exchange-product-table input[type='number'] {
        width: 60px;
        text-align: center;
    }

    .form-control:focus {
        box-shadow: none;
    }

    .quantity-container {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .search-bar {
        margin-bottom: 15px;
    }
    th, td {
        padding: 8px;  /* Reduced from 12px */
        font-size: 15px; /* Reduced from 16px */
    }

    #exchange-product-table th {
        background-color: #28a745;
        color: white;
    }

    #exchange-product-table input[type='number'] {
        width: 50px;  /* Reduced width */
        text-align: center;
    }

    .quantity-container {
        display: flex;
        align-items: center;
        justify-content: space-around;
    }
    .small-icon {
        font-size: 12px; /* You can adjust this size to fit your design */
    }
    #exchange-product-table .fa-times {
        font-size: 12px; /* Adjust this value to make the icon smaller or larger as needed */
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
    .quantity-container {
        display: flex;
        align-items: center;
        justify-content: space-between; /* Space between the buttons and the input */
        border: 1px solid #ccc;
        border-radius: 5px; /* Rounded corners for the entire container */
        padding: 3px;
    }

    .set-btn {
        width: 25px; /* Adjusted size */
        height: 25px; /* Adjusted size */
        font-size: 15px; /* Adjusted size */
        line-height: 1; /* Centers the icon vertically */
        padding: 0;
        border: none;
    }

    input.set-quantity {
        width: 50px;  /* Adjusted width */
        text-align: center;
        border: none; /* Remove border from input */
        outline: none; /* Remove outline when focused */
        font-size: 15px;
    }

    .btn-danger {
        background-color: #d9534f; /* Red color */
        color: white;
    }

    .btn-success {
        background-color: #5cb85c; /* Green color */
        color: white;
    }

</style>
{!! Form::open(['url' => route('sale-reward-supplier.store'), 'method' => 'POST', 'id' => 'supplier-reward-form']) !!}
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
                                        {!! Form::label('sell_list_filter_status', __('Ring Status') . ':') !!}
                                        {!! Form::select('sell_list_filter_status', ['' => __('All'), 'pending' => __('Pending'), 'send' => __('Send')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                                <div class="col-md-2"> </div>
                                <div class="col-md-8"> 
                                    <div class="search-bar" style="position: relative;">
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                            <input type="text" id="productSearch" class="form-control" placeholder="{{ __('Enter Product name / SKU / Scan bar code') }}">
                                        </div>
                                        <div id="search-results" style="position: absolute; width: 100%; background: white; z-index: 1000; border: 1px solid #ccc; display: none;"></div>
                                    </div>
                                </div>

                                <div class="col-md-2"> </div>

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
            url: "{{ route('sale-reward-supplier.getProduct') }}",
            method: 'GET',
            data: { id: productId },
            success: function(response) {
                if (response.name) {
                    addProductToTable(response);
                    clearSearchInput(); // Clear the search input after adding the product
                } else {
                    alert('Detailed product info not found.');
                }
            },
            error: function() {
                alert('Error fetching detailed product info.');
            }
        });
    }

    $searchInput.on('input', function() {
        var searchTerm = $(this).val();
        if (searchTerm.length > 0) {
            $.ajax({
                url: "{{ route('sale-reward-supplier.searchProduct') }}",
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
                error: function() {
                    $resultsDiv.html('<div class="search-item">Error fetching products.</div>').show();
                }
            });
        } else {
            $resultsDiv.hide();
        }
    });

    function addProductToTable(product) {
        var quantity = 1; // Default quantity for the new product
        var exchangeQuantity = product.exchange_quantity || 1; // Default exchange quantity if not provided
        var price = parseFloat(product.price).toFixed(2); // Format price to 2 decimal places
        var total = (quantity * price).toFixed(2); // Calculate total price

        // Generate the HTML for the new table row
        var newRow = `
            <tr>
                <td>${$tableBody.children().length + 1}</td>
                <td>${product.name} (${product.sku})
                    <input type="hidden" class="product-id" value="${product.id}">
                </td>
                <td>
                    <input type="hidden" class="exchange-quantity" value="${exchangeQuantity}">
                    ${exchangeQuantity}
                </td>
                <td>${product.receive_quantity}</td>
                <td class="quantity-container">
                    <button type="button" class="btn btn-danger set-btn" onclick="decrementQuantity(this)">-</button>
                    <input type="number" class="set-quantity" value="${quantity}" min="1" oninput="updateTotal(this)" />
                    <button type="button" class="btn btn-success set-btn" onclick="incrementQuantity(this)">+</button>
                </td>
                <td class="price">$${price}</td>
                <td class="total">$${total}</td>
                <td><button type="button" class="btn btn-danger fa fa-times" onclick="removeRow(this)"></button></td>
            </tr>`;
        $tableBody.append(newRow); // Add the new row to the table body
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
        $resultsDiv.hide(); // Hide search results after clicking a product
        clearSearchInput(); // Clear the search input after selecting a product
    });

    $(document).mouseup(function(e) {
        if (!$searchInput.is(e.target) && $resultsDiv.has(e.target).length === 0) {
            $resultsDiv.hide();
        }
    });

    // Function to clear the search input
    function clearSearchInput() {
            $searchInput.val('');
        }
    });

    // Make incrementQuantity and decrementQuantity globally accessible
    function incrementQuantity(button) {
        var input = $(button).siblings('input.set-quantity');
        var currentVal = parseInt(input.val());
        input.val(currentVal + 1);
        updateTotal(input);
    }

    function decrementQuantity(button) {
        var input = $(button).siblings('input.set-quantity');
        var currentVal = parseInt(input.val());
        if (currentVal > 1) { // Ensure value doesn't go below 1
            input.val(currentVal - 1);
            updateTotal(input);
        }
    }

    // Function to update the total price based on the quantity
    function updateTotal(input) {
        var quantity = parseInt($(input).val());
        var price = parseFloat($(input).closest('tr').find('.price').text().replace('$', ''));
        var total = (quantity * price).toFixed(2);
        $(input).closest('tr').find('.total').text('$' + total);
    }

    // Function to remove a row from the product table
    function removeRow(button) {
        $(button).closest('tr').remove();
        updateRowNumbers();
    }

    // Function to update the row numbers after a row is removed
    function updateRowNumbers() {
        $('#exchange-product-table tbody tr').each(function(index, row) {
            $(row).find('td:first').text(index + 1);
        });
    }
    
    $('#supplier-reward-form').on('submit', function(e) {
        e.preventDefault(); // Prevent the default form submission

        // Check if form is already being processed
        if ($(this).data('submitting') === true) {
            return false;
        }

        // Get the values of the required fields
        var contactId = $('#sell_list_filter_contact_id').val();
        var status = $('#sell_list_filter_status').val();

        // Check if the required fields are valid
        if (!contactId || contactId === '' || !status || status === '' || status === 'All') {
            alert('Please select a valid Supplier and Ring Status.');
            return false; // Do not proceed with form submission
        }

        var products = []; // Initialize an array to store product details

        // Iterate over each row in the table body
        $('#exchange-product-table tbody tr').each(function() {
            var product_id = $(this).find('.product-id').val();
            var quantity = $(this).find('.set-quantity').val();
            var price = parseFloat($(this).find('.price').text().replace('$', ''));
            var exchangeQuantity = $(this).find('.exchange-quantity').val(); // Get the exchange quantity

            products.push({
                product_id: product_id,
                quantity: quantity,
                exchange_quantity: exchangeQuantity, // Add exchange quantity to product details
                price: price
            });
        });

        if (products.length === 0) {
            alert('No products added to the table. Please add at least one product.');
            return false;
        }

        // If validation passes, submit the form with protection
        submitFormWithProtection(products);
    });

    function submitFormWithProtection(products) {
        const $form = $('#supplier-reward-form');
        const $submitBtn = $form.find('input[type="submit"]');
        
        // Mark form as being submitted
        $form.data('submitting', true);
        
        // Disable submit button and change text
        $submitBtn.prop('disabled', true).val('Processing...');
        
        // Add products data to form
        $('<input>').attr({
            type: 'hidden',
            id: 'products-data',
            name: 'products',
            value: JSON.stringify(products)
        }).appendTo($form); // Append the hidden input to the form

        // Submit the form
        $form[0].submit();
    }

</script>
@endsection
