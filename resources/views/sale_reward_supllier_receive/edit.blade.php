@extends('layouts.app')

@section('title', __('Edit Reward Stock Received'))

@section('content')
<section class="content-header">
    <h1>{{ __('Edit Reward Stock Received') }}</h1>
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
    .quantity-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 3px;
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
    #exchange-product-table th {
        background-color: #28a745;
        color: white;
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

    .btn-danger {
        background-color: #d9534f;
        color: white;
    }

    .btn-success {
        background-color: #5cb85c;
        color: white;
    }

    /* Delete button styling - Match create.blade.php */
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

{!! Form::open(['url' => route('sale-reward-supplier-receive.update', $transaction->id), 'method' => 'PUT', 'id' => 'supplier-reward-form']) !!}
<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('select_location_id', __('Location') . ':') !!}
                                {!! Form::hidden('select_location_id', $transaction->location_id) !!}
                                {!! Form::text('location_name', $transaction->location->name ?? '', ['class' => 'form-control', 'readonly']) !!}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('sell_list_filter_contact_id', __('Supplier') . ':') !!}
                                {!! Form::select('sell_list_filter_contact_id', $contact, $transaction->contact_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => 'Select Supplier']) !!}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('invoice_no', __('Reference No') . ':') !!}
                                {!! Form::text('invoice_no', $transaction->ref_no, ['class' => 'form-control']) !!}
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('transaction_date', __('Date') . ':') !!}
                                {!! Form::text('transaction_date', $transaction->transaction_date, ['class' => 'form-control', 'id' => 'transaction_date']) !!}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('status', __('Status') . ':') !!}
                                {!! Form::select('status', ['pending' => 'Pending', 'completed' => 'Completed'], $transaction->status, ['class' => 'form-control select2', 'required']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="box">
                <div class="box-body">
                    <!-- Search Section -->
                    <div class="row">
                        <div class="col-md-12">
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
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="row">
                        <div class="col-md-12">
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
                                    @foreach($transaction->sell_lines as $index => $line)
                                        @php
                                            $rewards = $rewards_exchange[$line->product_id] ?? null;
                                            $exchange_quantity = $rewards ? $rewards->exchange_quantity : 0;
                                            $receive_quantity = $rewards ? $rewards->receive_quantity : 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $line->product->name }}
                                                <input type="hidden" class="product-id" name="products[{{ $index }}][product_id]" value="{{ $line->product_id }}">
                                            </td>
                                            <td>{{ $exchange_quantity }}</td>
                                            <td>{{ $receive_quantity }}</td>
                                            <td class="quantity-container">
                                                <button type="button" class="btn btn-danger set-btn" onclick="decrementQuantity(this)">-</button>
                                                <input type="number" class="set-quantity" name="products[{{ $index }}][quantity]" value="{{ $line->quantity }}" min="1" oninput="updateTotal(this)" />
                                                <button type="button" class="btn btn-success set-btn" onclick="incrementQuantity(this)">+</button>
                                            </td>
                                            <td class="price">${{ number_format($line->unit_price, 2) }}
                                                <input type="hidden" name="products[{{ $index }}][price]" value="{{ $line->unit_price }}">
                                            </td>
                                            <td class="total">${{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                                            <td><button type="button" class="delete-product-btn" onclick="removeRow(this)" title="Delete"><i class="fa fa-times"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                {!! Form::label('note', __('Note') . ':') !!}
                                {!! Form::textarea('note', $transaction->additional_notes, ['class' => 'form-control', 'rows' => 3]) !!}
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                        <a href="{{ route('sale-reward-supplier-receive.index') }}" class="btn btn-default">{{ __('Cancel') }}</a>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</section>
{!! Form::close() !!}
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    $('.select2').select2();

    $('#transaction_date').daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        timePicker: true,
        timePicker24Hour: true,
        timePickerSeconds: true,
        locale: { format: 'YYYY-MM-DD HH:mm:ss' },
        startDate: '{{ $transaction->transaction_date }}',
        autoUpdateInput: true
    });

    var $searchInput = $('#productSearch');
    var $resultsDiv = $('#search-results');
    var $tableBody = $('#exchange-product-table tbody');

    // Fetch and add product to table
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
                    alert('Product info not found.');
                }
            },
            error: function() {
                alert('Error fetching product info.');
            }
        });
    }

    // Search products
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
                        var exactMatch = products.find(p => p.product.name.toLowerCase() === searchTerm.toLowerCase());
                        if (exactMatch) {
                            fetchAndAddProduct(exactMatch.product.id);
                            $resultsDiv.hide();
                            clearSearchInput();
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

    // Add product to table
    function addProductToTable(product) {
        // Check if product already exists in table
        var productExists = false;
        $tableBody.find('.product-id').each(function() {
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

        var newRow = `
            <tr>
                <td>${rowCount + 1}</td>
                <td>${product.name} (${product.sku})
                    <input type="hidden" class="product-id" name="products[${rowCount}][product_id]" value="${product.id}">
                </td>
                <td>${exchangeQuantity}</td>
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
        $tableBody.append(newRow);
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

    // Global functions for quantity adjustment
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
        $('#exchange-product-table tbody tr').each((index, row) => {
            $(row).find('td:first').text(index + 1);
        });
    }

    // Form submission
    $('#supplier-reward-form').submit(function(event) {
        event.preventDefault();

        if ($(this).data('submitting') === true) {
            return false;
        }

        var status = $('#status').val();
        if (!status) {
            alert('Please select a valid status.');
            return false;
        }

        if ($('#exchange-product-table tbody tr').length === 0) {
            alert('Please add at least one product.');
            return false;
        }

        var products = [];
        $('#exchange-product-table tbody tr').each(function() {
            var product_id = $(this).find('.product-id').val();
            var quantity = $(this).find('.set-quantity').val();
            var price = parseFloat($(this).find('.price').text().replace('$', ''));

            products.push({
                product_id: product_id,
                quantity: quantity,
                price: price
            });
        });

        submitFormWithProtection(products, status);
    });

    function submitFormWithProtection(products, status) {
        const $form = $('#supplier-reward-form');
        const $submitBtn = $form.find('button[type="submit"]');
        
        $form.data('submitting', true);
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
        
        var formData = new FormData();
        formData.append('status', status);
        formData.append('note', $('textarea[name="note"]').val());
        formData.append('sell_list_filter_contact_id', $('#sell_list_filter_contact_id').val());
        formData.append('invoice_no', $('input[name="invoice_no"]').val());
        formData.append('transaction_date', $('#transaction_date').val());
        formData.append('select_location_id', '{{ $transaction->location_id }}');
        formData.append('products', JSON.stringify(products));
        formData.append('_method', 'PUT');
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: "{{ route('sale-reward-supplier-receive.update', $transaction->id) }}",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                window.location.href = "{{ route('sale-reward-supplier-receive.index') }}";
            },
            error: function(xhr) {
                $form.data('submitting', false);
                $submitBtn.prop('disabled', false).html('Update');
                
                var errorMsg = 'Error updating transaction.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    }
});
</script>
@endsection