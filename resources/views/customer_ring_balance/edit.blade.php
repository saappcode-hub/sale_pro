@extends('layouts.app')

@section('title', __('Edit Top Up Ring Balance'))

@section('content')
<section class="content-header">
    <h1>{{ __('Edit Top Up Ring Balance') }}</h1>
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
        margin-top: 10px;
    }
    .cash-ring-amount {
        margin-top: 5px;
        color: #dc3545;
        font-size: 12px;
        font-weight: normal;
    }
    .contact-type-toggle {
        display: inline-flex;
        gap: 4px;
    }
    .toggle-btn {
        padding: 4px 8px;
        border: 1px solid #007bff;
        background-color: #f8f9fa;
        color: #007bff;
        border-radius: 3px;
        cursor: pointer;
        font-weight: 500;
        font-size: 9px;
        transition: all 0.2s ease;
    }
    .toggle-btn.active {
        background-color: #007bff;
        color: white;
    }
    .toggle-btn:hover {
        background-color: #0056b3;
        color: white;
        border-color: #0056b3;
    }
</style>

{!! Form::model($transaction, ['url' => route('customer-ring-balance.update', $transaction->id), 'method' => 'PUT', 'id' => 'customer-ring-balance-form']) !!}
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
                                {!! Form::select('select_location_id', $business_locations, $transaction->location_id, ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required']) !!}
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 2px;">
                                        {!! Form::label('sell_list_filter_contact_id', __('Select Contact') . ':', ['style' => 'margin-bottom: 0;']) !!}
                                        <div class="contact-type-toggle">
                                            <button type="button" class="toggle-btn active" data-type="customer" id="customer-toggle">
                                                <i class="fa fa-user"></i> Customer
                                            </button>
                                            <button type="button" class="toggle-btn" data-type="supplier" id="supplier-toggle">
                                                <i class="fa fa-building"></i> Supplier
                                            </button>
                                        </div>
                                    </div>
                                    {!! Form::select('sell_list_filter_contact_id', $contact, $transaction->contact_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_contact_id']) !!}
                                    {!! Form::hidden('contact_type', 'customer', ['id' => 'contact_type']) !!}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! Form::label('invoice_sell_no', __('Invoice Sell No') . ':') !!}
                                    <div class="input-group">
                                        {!! Form::text('invoice_sell_no', $transaction->sell_ref_invoice, ['class' => 'form-control', 'placeholder' => __('Enter Invoice Sell Number'), 'id' => 'invoice_sell_no']) !!}
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-info" id="check-invoice-btn">
                                                <i class="fa fa-check"></i> Check
                                            </button>
                                        </span>
                                    </div>
                                    <div id="cash-ring-display" class="cash-ring-amount" style="display: none;"></div>
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

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! Form::label('status', __('Status') . ':') !!}
                                    {!! Form::select('status', ['pending' => __('Pending'), 'completed' => __('Completed')], $transaction->status, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'status']) !!}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! Form::label('exchange_rate', __('Exchange Rate (៛)') . ':') !!}
                                    {!! Form::number('exchange_rate', isset($transaction->exchange_rate) ? (int)$transaction->exchange_rate : 4000, ['class' => 'form-control', 'id' => 'exchange_rate', 'step' => '1', 'min' => '0']) !!}
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
                                <div id="product-suggestions" class="list-group" style="display: none; position: absolute; z-index: 1000; width: 60%; margin-left: 20%;"></div>
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
                                        @foreach($transaction->transactionSellRingBalances as $product)
                                            @php
                                                $productType = $product->cash_ring ? 'cash' : 'customer';
                                                $uniqueId = $product->product_id . '_' . $productType;
                                                $displayName = $product->product->name . ($productType === 'cash' ? ' (Cash Ring)' : ' (Customer Ring)');
                                            @endphp
                                            
                                            <tr data-unique-id="{{ $uniqueId }}" data-product-id="{{ $product->product_id }}" data-type="{{ $productType }}">
                                                <td>{{ $displayName }}</td>
                                                <td>
                                                    @if($productType === 'customer')
                                                        @php
                                                            $ringUnits = \App\RingUnit::where('business_id', session('user.business_id'))
                                                                ->where('product_id', $product->product_id)
                                                                ->pluck('value')
                                                                ->sort()
                                                                ->toArray();
                                                            $ringUnitQuantities = $product->ringUnits->pluck('quantity_ring', 'ring_units_id')->toArray();
                                                        @endphp
                                                        @if(!empty($ringUnits))
                                                            @foreach($ringUnits as $unit)
                                                                @php
                                                                    $ringUnit = \App\RingUnit::where('business_id', session('user.business_id'))
                                                                        ->where('product_id', $product->product_id)
                                                                        ->where('value', $unit)
                                                                        ->first();
                                                                    $quantity = 0;
                                                                    if ($ringUnit && isset($ringUnitQuantities[$ringUnit->id])) {
                                                                        $quantity = intval($ringUnitQuantities[$ringUnit->id]);
                                                                    }
                                                                @endphp
                                                                <div class="ring-unit-row" data-ring-value="{{ $unit }}">
                                                                    <span class="ring-unit-label">{{ $unit }} Can:</span>
                                                                    <div class="input-group quantity-input-group">
                                                                        <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                                                        <input type="number" class="form-control set-quantity" 
                                                                               name="products[{{ $uniqueId }}][quantities][{{ $unit }}]" 
                                                                               min="0" step="1" value="{{ $quantity }}" data-ring-value="{{ $unit }}">
                                                                        <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        @else
                                                            <div class="ring-unit-row" data-ring-value="1">
                                                                <span class="ring-unit-label">1 Ring:</span>
                                                                <div class="input-group quantity-input-group">
                                                                    <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                                                    <input type="number" class="form-control set-quantity" 
                                                                           name="products[{{ $uniqueId }}][quantities][1]" 
                                                                           min="0" step="1" value="{{ intval($product->quantity) }}" data-ring-value="1">
                                                                    <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="total-rings">
                                                            Total: <span class="total-rings-value" data-unique-id="{{ $uniqueId }}">{{ intval($product->quantity) }}</span> Can
                                                        </div>
                                                    @else
                                                        @php
                                                            $cashRingBalances = \App\CashRingBalance::where('business_id', session('user.business_id'))
                                                                ->where('product_id', $product->product_id)
                                                                ->get(['unit_value', 'type_currency', 'redemption_value', 'id']);
                                                            
                                                            $groupedCashRings = ['dollar' => [], 'riel' => []];
                                                            foreach ($cashRingBalances as $cashRingBalance) {
                                                                $currencySymbol = $cashRingBalance->type_currency == 1 ? '$' : '៛';
                                                                $currencyType = $cashRingBalance->type_currency == 1 ? 'dollar' : 'riel';
                                                                $groupedCashRings[$currencyType][] = [
                                                                    'id' => $cashRingBalance->id,
                                                                    'unit_value' => $cashRingBalance->unit_value,
                                                                    'currency_symbol' => $currencySymbol,
                                                                    'redemption_value' => $cashRingBalance->redemption_value
                                                                ];
                                                            }
                                                            $cashRingQuantities = $product->cashRings->pluck('quantity', 'cash_ring_balance_id')->toArray();
                                                        @endphp

                                                        <div style="display: flex; align-items: flex-start; gap: 50px;">
                                                            @if(count($groupedCashRings['dollar']) > 0)
                                                                <div style="display: inline-block; vertical-align: top; margin-right: 50px;">
                                                                    <div style="font-weight: bold; margin-bottom: 10px;">Dollar ($)</div>
                                                                    @foreach($groupedCashRings['dollar'] as $cashRing)
                                                                        @php
                                                                            $quantity = isset($cashRingQuantities[$cashRing['id']]) ? $cashRingQuantities[$cashRing['id']] : 0;
                                                                        @endphp
                                                                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                                                            <span style="min-width: 60px; margin-right: 10px;">{{ $cashRing['unit_value'] }}{{ $cashRing['currency_symbol'] }} :</span>
                                                                            <div class="input-group quantity-input-group">
                                                                                <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                                                                <input type="number" class="form-control set-quantity" 
                                                                                    name="products[{{ $uniqueId }}][cash_quantities][{{ $cashRing['id'] }}]" 
                                                                                    min="0" value="{{ $quantity }}" 
                                                                                    data-cash-ring-id="{{ $cashRing['id'] }}"
                                                                                    data-unit-value="{{ $cashRing['unit_value'] }}" 
                                                                                    data-redemption-value="{{ $cashRing['redemption_value'] }}"
                                                                                    data-currency="dollar">
                                                                                <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                    <div style="margin-top: 10px; font-weight: bold;">
                                                                        Total: <span class="total-currency-value" data-currency="dollar" data-unique-id="{{ $uniqueId }}">0</span>$
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if(count($groupedCashRings['riel']) > 0)
                                                                <div style="display: inline-block; vertical-align: top;">
                                                                    <div style="font-weight: bold; margin-bottom: 10px;">Riel (៛)</div>
                                                                    @foreach($groupedCashRings['riel'] as $cashRing)
                                                                        @php
                                                                            $quantity = isset($cashRingQuantities[$cashRing['id']]) ? $cashRingQuantities[$cashRing['id']] : 0;
                                                                        @endphp
                                                                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                                                            <span style="min-width: 80px; margin-right: 10px;">{{ $cashRing['unit_value'] }}{{ $cashRing['currency_symbol'] }} :</span>
                                                                            <div class="input-group quantity-input-group">
                                                                                <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                                                                <input type="number" class="form-control set-quantity" 
                                                                                    name="products[{{ $uniqueId }}][cash_quantities][{{ $cashRing['id'] }}]" 
                                                                                    min="0" value="{{ $quantity }}" 
                                                                                    data-cash-ring-id="{{ $cashRing['id'] }}"
                                                                                    data-unit-value="{{ $cashRing['unit_value'] }}" 
                                                                                    data-redemption-value="{{ $cashRing['redemption_value'] }}"
                                                                                    data-currency="riel">
                                                                                <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                    <div style="margin-top: 10px; font-weight: bold;">
                                                                        Total: <span class="total-currency-value" data-currency="riel" data-unique-id="{{ $uniqueId }}">0</span>៛
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td style="text-align: center; vertical-align: middle;">
                                                    <button type="button" class="remove-product-btn">Delete</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12" style="text-align: right; font-weight: bold; font-size: 16px; margin-top: 10px; margin-bottom: 10px;">
                                <span style="margin-right: 20px;">
                                    Total Riel: <span id="grand-total-riel">0.00</span>៛
                                </span>
                                <span>
                                    Total Dollar: $<span id="grand-total-dollar">0.00</span>
                                </span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {!! Form::label('noted', __('Note') . ':') !!}
                                    {!! Form::textarea('noted', $transaction->noted, ['class' => 'form-control', 'id' => 'noted', 'rows' => 3, 'placeholder' => __('Enter any additional notes...')]) !!}
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
function updateTotalRings(uniqueId) {
    let totalRings = 0;
    $(`tr[data-unique-id="${uniqueId}"] .set-quantity`).each(function() {
        let quantity = parseInt($(this).val()) || 0;
        let ringValue = parseFloat($(this).data('ring-value') || $(this).data('redemption-value') || 0);
        if (ringValue) {
            totalRings += quantity * ringValue;
        }
    });
    $(`tr[data-unique-id="${uniqueId}"] .total-rings-value`).text(totalRings);
}

function updateCashRingTotals(uniqueId) {
    let dollarTotal = 0;
    $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="dollar"]`).each(function() {
        let quantity = parseInt($(this).val()) || 0;
        let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
        dollarTotal += quantity * redemptionValue;
    });
    $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="dollar"]`).text(dollarTotal.toFixed(2));

    let rielTotal = 0;
    $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="riel"]`).each(function() {
        let quantity = parseInt($(this).val()) || 0;
        let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
        rielTotal += quantity * redemptionValue;
    });
    $(`tr[data-unique-id="${uniqueId}"] .total-currency-value[data-currency="riel"]`).text(rielTotal.toFixed(2));
}

function updateGrandTotals() {
    let grandTotalRiel = 0;
    let grandTotalDollar = 0;

    $('#exchange-product-table .set-quantity[data-currency="dollar"]').each(function() {
        let quantity = parseInt($(this).val()) || 0;
        let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
        grandTotalDollar += quantity * redemptionValue;
    });

    $('#exchange-product-table .set-quantity[data-currency="riel"]').each(function() {
        let quantity = parseInt($(this).val()) || 0;
        let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
        grandTotalRiel += quantity * redemptionValue;
    });

    $('#grand-total-riel').text(grandTotalRiel.toFixed(2));
    $('#grand-total-dollar').text(grandTotalDollar.toFixed(2));
}

function formatContact(contact) {
    if (!contact.id) return contact.text;
    return $('<div>' + contact.text.split('<br>').join('</div><div>') + '</div>');
}

function formatContactSelection(contact) {
    if (!contact.id) return contact.text;
    return contact.text.split(' (')[0].trim();
}

function displayCashRingAmount(data) {
    let displayHtml = `Total Cash Ring: $${data.total_cash_ring.toFixed(2)}`;
    $('#cash-ring-display')
        .html(displayHtml)
        .attr('data-invoice-total', data.total_cash_ring)
        .show();
}

function calculateTotalProductCashRing() {
    let totalProductCashRing = 0;
    $('#exchange-product-table tbody tr[data-type="cash"]').each(function() {
        let uniqueId = $(this).data('unique-id');
        $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="dollar"]`).each(function() {
            let quantity = parseInt($(this).val()) || 0;
            let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
            totalProductCashRing += quantity * redemptionValue;
        });
        $(`tr[data-unique-id="${uniqueId}"] .set-quantity[data-currency="riel"]`).each(function() {
            let quantity = parseInt($(this).val()) || 0;
            let redemptionValue = parseFloat($(this).data('redemption-value') || 0);
            totalProductCashRing += (quantity * redemptionValue) / 4000;
        });
    });
    return totalProductCashRing;
}

function showInsufficientCashRingPopup(productTotal, invoiceTotal) {
    return new Promise((resolve) => {
        let popupHtml = `<div id="validation-popup" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="margin-bottom: 20px;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background-color: #17a2b8; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">!</div>
                    <h4 style="margin: 0; font-weight: bold;">salepro.asia</h4>
                </div>
                <p style="margin-bottom: 25px; color: #666; line-height: 1.5;">
                    The cash ring amount entered is less than the total cash ring amount in the invoice.<br>
                    Do you want to continue with this top-up?
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button id="popup-cancel" style="background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                    <button id="popup-continue" style="background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">Top Up</button>
                </div>
            </div>
        </div>`;
        
        $('body').append(popupHtml);
        $('#popup-cancel').on('click', function() {
            $('#validation-popup').remove();
            resolve(false);
        });
        $('#popup-continue').on('click', function() {
            $('#validation-popup').remove();
            resolve(true);
        });
    });
}

$(document).ready(function() {
    let customerContacts = {!! json_encode($contact) !!};
    let supplierContacts = {!! json_encode($suppliers ?? []) !!};
    let contactType = '{{ $contactType ?? "customer" }}'; // Get contact type from controller
    let currentContactType = contactType; // Set to transaction's actual type
    let selectedContactId = {{ $transaction->contact_id }}; // Get the selected contact ID
    let originalContactType = contactType; // Store the original contact type (cannot be changed)

    // Initialize date picker
    $('#transaction_date').daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        timePicker: true,
        timePicker24Hour: true,
        timePickerSeconds: true,
        locale: { format: 'YYYY-MM-DD HH:mm:ss' },
        startDate: moment('{{ $transaction->transaction_date }}').format('YYYY-MM-DD HH:mm:ss'),
        autoUpdateInput: true
    });

    $('#transaction_date').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm:ss'));
    });

    // Function to populate contacts based on type
    function populateContacts(type, selectId = null) {
        let contacts = type === 'supplier' ? supplierContacts : customerContacts;
        
        $('#sell_list_filter_contact_id').select2('destroy');
        $('#sell_list_filter_contact_id').empty();
        
        Object.entries(contacts).forEach(([id, text]) => {
            $('#sell_list_filter_contact_id').append(
                $('<option></option>').val(id).html(text)
            );
        });
        
        $('#sell_list_filter_contact_id').select2({
            templateResult: formatContact,
            templateSelection: formatContactSelection,
            width: '100%'
        });

        // Set the selected contact after populating
        if (selectId) {
            $('#sell_list_filter_contact_id').val(selectId).trigger('change');
        }
    }

    // Initialize select2 with correct contacts and select the correct contact
    populateContacts(contactType, selectedContactId);

    // Set correct toggle as active based on transaction type
    $('.toggle-btn').removeClass('active');
    if (contactType === 'supplier') {
        $('#supplier-toggle').addClass('active');
        $('#contact_type').val('supplier');
        // Disable customer toggle for supplier transactions
        $('#customer-toggle').css('opacity', '0.5').css('cursor', 'not-allowed').prop('disabled', true);
        $('#customer-toggle').attr('title', 'Cannot change from Supplier to Customer');
    } else {
        $('#customer-toggle').addClass('active');
        $('#contact_type').val('customer');
        // Disable supplier toggle for customer transactions
        $('#supplier-toggle').css('opacity', '0.5').css('cursor', 'not-allowed').prop('disabled', true);
        $('#supplier-toggle').attr('title', 'Cannot change from Customer to Supplier');
    }

    // Customer toggle click (only works if transaction was created as customer)
    $('#customer-toggle').on('click', function(e) {
        e.preventDefault();
        
        // Check if this toggle is disabled
        if (originalContactType !== 'customer') {
            alert('This transaction was created as a Supplier transaction. You can only switch between other suppliers.');
            return false;
        }

        currentContactType = 'customer';
        $('#contact_type').val('customer');
        $('.toggle-btn').removeClass('active');
        $(this).addClass('active');
        populateContacts('customer');
        $('#sell_list_filter_contact_id').val('').trigger('change');
    });

    // Supplier toggle click (only works if transaction was created as supplier)
    $('#supplier-toggle').on('click', function(e) {
        e.preventDefault();
        
        // Check if this toggle is disabled
        if (originalContactType !== 'supplier') {
            alert('This transaction was created as a Customer transaction. You can only switch between other customers.');
            return false;
        }

        currentContactType = 'supplier';
        $('#contact_type').val('supplier');
        $('.toggle-btn').removeClass('active');
        $(this).addClass('active');
        populateContacts('supplier');
        $('#sell_list_filter_contact_id').val('').trigger('change');
    });

    // Check invoice button
    $('#check-invoice-btn').on('click', function() {
        let invoiceSellNo = $('#invoice_sell_no').val().trim();
        if (!invoiceSellNo) {
            alert('Please enter an invoice sell number.');
            return;
        }
        $.ajax({
            url: '{{ route("customer-ring-balance.checkInvoiceSell") }}',
            method: 'GET',
            data: { invoice_no: invoiceSellNo },
            success: function(response) {
                displayCashRingAmount(response);
                if (response.contact_id) {
                    $('#sell_list_filter_contact_id').val(response.contact_id).trigger('change');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error checking invoice:', error);
                $('#cash-ring-display').html('Error checking invoice. Please try again.').show();
            }
        });
    });

    // Product search
    $('#search_product').on('keyup', function() {
        let searchTerm = $(this).val();
        if (searchTerm.length >= 2) {
            $.ajax({
                url: '{{ route("customer-ring-balance.searchProduct") }}',
                method: 'GET',
                data: { query: searchTerm },
                success: function(response) {
                    $('#product-suggestions').empty().show();
                    response.products.forEach(function(product) {
                        $('#product-suggestions').append(
                            `<a href="#" class="list-group-item list-group-item-action" data-id="${product.id}" data-product-id="${product.product_id}" data-name="${product.name}" data-sku="${product.sku}" data-type="${product.type}" data-display-name="${product.display_name}" data-cash-ring-values='${JSON.stringify(product.cash_ring_values || {})}'>
                                <strong>${product.display_name}</strong> - ${product.sku}
                            </a>`
                        );
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

    // Handle product selection
    $(document).on('click', '#product-suggestions .list-group-item', function(e) {
        e.preventDefault();
        let productData = $(this).data();
        let uniqueId = productData.id;
        let productId = productData.productId;
        let displayName = productData.displayName;
        let productType = productData.type;
        let cashRingValues = productData.cashRingValues || { dollar: [], riel: [] };

        if ($(`#exchange-product-table tr[data-unique-id="${uniqueId}"]`).length > 0) {
            alert('This product option is already added.');
            return;
        }

        if (productType === 'customer') {
            $.ajax({
                url: '{{ route("customer-ring-balance.getRingUnits") }}',
                method: 'GET',
                data: { product_id: productId },
                success: function(response) {
                    let ringUnits = response.ring_units;
                    let quantityInputs = '';
                    
                    if (ringUnits.length > 0) {
                        ringUnits.sort((a, b) => a - b);
                        ringUnits.forEach(function(unit) {
                            quantityInputs += `<div class="ring-unit-row" data-ring-value="${unit}">
                                <span class="ring-unit-label">${unit} Can:</span>
                                <div class="input-group quantity-input-group">
                                    <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                    <input type="number" class="form-control set-quantity" name="products[${uniqueId}][quantities][${unit}]" min="0" value="0" data-ring-value="${unit}">
                                    <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                                </div>
                            </div>`;
                        });
                    } else {
                        let defaultUnit = 1;
                        quantityInputs = `<div class="ring-unit-row" data-ring-value="${defaultUnit}">
                            <span class="ring-unit-label">${defaultUnit} Ring:</span>
                            <div class="input-group quantity-input-group">
                                <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                                <input type="number" class="form-control set-quantity" name="products[${uniqueId}][quantities][${defaultUnit}]" min="0" value="0" data-ring-value="${defaultUnit}">
                                <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                            </div>
                        </div>`;
                    }
                    
                    quantityInputs += `<div class="total-rings">Total: <span class="total-rings-value" data-unique-id="${uniqueId}">0</span> Can</div>`;
                    
                    $('#exchange-product-table tbody').append(
                        `<tr data-unique-id="${uniqueId}" data-product-id="${productId}" data-type="${productType}">
                            <td>${displayName}</td>
                            <td>${quantityInputs}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="remove-product-btn">Delete</button>
                            </td>
                        </tr>`
                    );
                    updateTotalRings(uniqueId);
                },
                error: function() {
                    alert('Error fetching ring units for this product.');
                }
            });
        } else if (productType === 'cash') {
            let dollarSection = '';
            let rielSection = '';

            if (cashRingValues.dollar && cashRingValues.dollar.length > 0) {
                dollarSection = '<div style="display: inline-block; vertical-align: top; margin-right: 50px;"><div style="font-weight: bold; margin-bottom: 10px;">Dollar ($)</div>';
                cashRingValues.dollar.forEach(function(cashRing) {
                    dollarSection += `<div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <span style="min-width: 60px; margin-right: 10px;">${cashRing.unit_value}$ :</span>
                        <div class="input-group quantity-input-group">
                            <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                            <input type="number" class="form-control set-quantity" name="products[${uniqueId}][cash_quantities][${cashRing.id}]" min="0" value="0" data-cash-ring-id="${cashRing.id}" data-redemption-value="${cashRing.redemption_value}" data-currency="dollar">
                            <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                        </div>
                    </div>`;
                });
                dollarSection += `<div style="margin-top: 10px; font-weight: bold;">Total: <span class="total-currency-value" data-currency="dollar" data-unique-id="${uniqueId}">0</span>$</div></div>`;
            }

            if (cashRingValues.riel && cashRingValues.riel.length > 0) {
                rielSection = '<div style="display: inline-block; vertical-align: top;"><div style="font-weight: bold; margin-bottom: 10px;">Riel (៛)</div>';
                cashRingValues.riel.forEach(function(cashRing) {
                    rielSection += `<div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <span style="min-width: 80px; margin-right: 10px;">${cashRing.unit_value}៛ :</span>
                        <div class="input-group quantity-input-group">
                            <button type="button" class="btn btn-outline-danger qty-btn" data-action="decrease">-</button>
                            <input type="number" class="form-control set-quantity" name="products[${uniqueId}][cash_quantities][${cashRing.id}]" min="0" value="0" data-cash-ring-id="${cashRing.id}" data-redemption-value="${cashRing.redemption_value}" data-currency="riel">
                            <button type="button" class="btn btn-outline-success qty-btn" data-action="increase">+</button>
                        </div>
                    </div>`;
                });
                rielSection += `<div style="margin-top: 10px; font-weight: bold;">Total: <span class="total-currency-value" data-currency="riel" data-unique-id="${uniqueId}">0</span>៛</div></div>`;
            }

            $('#exchange-product-table tbody').append(
                `<tr data-unique-id="${uniqueId}" data-product-id="${productId}" data-type="${productType}">
                    <td>${displayName}</td>
                    <td>${dollarSection}${rielSection}</td>
                    <td style="text-align: center; vertical-align: middle;">
                        <button type="button" class="remove-product-btn">Delete</button>
                    </td>
                </tr>`
            );
            updateCashRingTotals(uniqueId);
            updateGrandTotals();
        }

        $('#product-suggestions').hide();
        $('#search_product').val('');
    });

    // Quantity button handlers
    $(document).on('click', '.qty-btn', function() {
        let $input = $(this).siblings('input.set-quantity');
        let currentValue = parseInt($input.val()) || 0;
        if ($(this).data('action') === 'increase') {
            $input.val(currentValue + 1);
        } else if ($(this).data('action') === 'decrease' && currentValue > 0) {
            $input.val(currentValue - 1);
        }
        let uniqueId = $(this).closest('tr').data('unique-id');
        let productType = $(this).closest('tr').data('type');
        if (productType === 'customer') {
            updateTotalRings(uniqueId);
        } else if (productType === 'cash') {
            updateCashRingTotals(uniqueId);
        }
        updateGrandTotals();
    });

    // Quantity input handlers
    $(document).on('input', '.set-quantity', function() {
        let $input = $(this);
        let value = parseInt($input.val()) || 0;
        if (value < 0) {
            $input.val(0);
            value = 0;
        }
        let uniqueId = $(this).closest('tr').data('unique-id');
        let productType = $(this).closest('tr').data('type');
        if (productType === 'customer') {
            updateTotalRings(uniqueId);
        } else if (productType === 'cash') {
            updateCashRingTotals(uniqueId);
        }
        updateGrandTotals();
    });

    // Remove product button
    $(document).on('click', '.remove-product-btn', function() {
        $(this).closest('tr').remove();
        updateGrandTotals();
    });

    // Auto-trigger invoice check if it exists
    @if($transaction->sell_ref_invoice)
        $('#check-invoice-btn').trigger('click');
    @endif

    // Form submission
    $('#customer-ring-balance-form').submit(function(e) {
        e.preventDefault();

        let locationId = $('#select_location_id').val();
        if (!locationId || locationId === '') {
            alert('Please select a location.');
            $('#select_location_id').focus();
            return false;
        }

        let customerId = $('#sell_list_filter_contact_id').val();
        if (!customerId || customerId === '' || customerId === 'all') {
            alert('Please select a contact.');
            $('#sell_list_filter_contact_id').focus();
            return false;
        }

        let transactionDate = $('#transaction_date').val();
        if (!transactionDate || transactionDate.trim() === '') {
            alert('Please select a transaction date.');
            $('#transaction_date').focus();
            return false;
        }

        let status = $('#status').val();
        if (!status || (status !== 'pending' && status !== 'completed')) {
            alert('Please select a valid status (Pending or Completed).');
            $('#status').focus();
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

        let hasCashRingProducts = $('#exchange-product-table tbody tr[data-type="cash"]').length > 0;
        if (hasCashRingProducts) {
            let productTotal = calculateTotalProductCashRing();
            let invoiceTotal = parseFloat($('#cash-ring-display').attr('data-invoice-total') || 0);

            if (productTotal < invoiceTotal) {
                showInsufficientCashRingPopup(productTotal, invoiceTotal).then((shouldContinue) => {
                    if (shouldContinue) {
                        $('#customer-ring-balance-form')[0].submit();
                    }
                });
                return false;
            }
        }

        $('#customer-ring-balance-form')[0].submit();
    });

    // Initialize totals for existing products
    $('#exchange-product-table tbody tr').each(function() {
        let uniqueId = $(this).data('unique-id');
        let productType = $(this).data('type');
        if (productType === 'customer') {
            updateTotalRings(uniqueId);
        } else if (productType === 'cash') {
            updateCashRingTotals(uniqueId);
        }
    });

    updateGrandTotals();
});
</script>
@endsection