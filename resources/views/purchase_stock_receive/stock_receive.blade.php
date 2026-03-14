@extends('layouts.app')
@section('title', __('Stock Receive'))

@section('content')

@php
    $custom_labels = json_decode(session('business.custom_labels'), true);
@endphp
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('Stock Receive')</h1>
</section>

<!-- Main content -->
<section class="content">
    @include('layouts.partials.error')

    {!! Form::open(['url' => action([\App\Http\Controllers\PurchaseStockReceiveController::class, 'store']), 'method' => 'post', 'id' => 'stock_receive_form', 'files' => true ]) !!}
    
    {{-- Hidden fields to track the purchase order --}}
    @if(isset($purchase_order) && $purchase_order)
        {!! Form::hidden('purchase_order_id', $purchase_order->id) !!}
        {!! Form::hidden('purchase_order_ref', $purchase_order->ref_no) !!}
    @else
        {!! Form::hidden('purchase_order_id', null, ['id' => 'hidden_purchase_order_id']) !!}
        {!! Form::hidden('purchase_order_ref', null, ['id' => 'hidden_purchase_order_ref']) !!}
    @endif

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('supplier_id', __('purchase.supplier') . ':*') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-user"></i>
                        </span>
                        {!! Form::select('contact_id', $suppliers, (isset($purchase_order) && $purchase_order) ? $purchase_order->contact_id : null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required', 'id' => 'supplier_id']); !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default bg-white btn-flat add_new_supplier" data-name=""><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('ref_no', __('purchase.ref_no').':') !!}
                    {!! Form::text('ref_no', null, ['class' => 'form-control']); !!}
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('transaction_date', __('purchase.purchase_date') . ':*') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-calendar"></i>
                        </span>
                        {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'readonly', 'required']); !!}
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('status', __('purchase.purchase_status') . ':*') !!}
                    {!! Form::select('status', ['received' => __('lang_v1.received')], 'received', ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-3">
                <strong>
                    @lang('business.address'):
                </strong>
                <div id="supplier_address_div">
                    @if(isset($purchase_order) && $purchase_order && isset($purchase_order->contact) && $purchase_order->contact)
                        @if($purchase_order->contact->supplier_business_name)
                            {{ $purchase_order->contact->supplier_business_name }}<br>
                        @endif
                        @if($purchase_order->contact->address_line_1)
                            {{ $purchase_order->contact->address_line_1 }}<br>
                        @endif
                        @if($purchase_order->contact->address_line_2)
                            {{ $purchase_order->contact->address_line_2 }}<br>
                        @endif
                        @if($purchase_order->contact->city)
                            {{ $purchase_order->contact->city }}, 
                        @endif
                        @if($purchase_order->contact->state)
                            {{ $purchase_order->contact->state }}, 
                        @endif
                        @if($purchase_order->contact->country)
                            {{ $purchase_order->contact->country }} 
                        @endif
                        @if($purchase_order->contact->zip_code)
                            {{ $purchase_order->contact->zip_code }}
                        @endif
                    @endif
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('location_id', __('purchase.business_location').':*') !!}
                    {!! Form::select('location_id', $business_locations, (isset($purchase_order) && $purchase_order) ? $purchase_order->location_id : null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <div class="multi-input">
                        {!! Form::label('pay_term_number', __('contact.pay_term') . ':') !!}
                        <br/>
                        {!! Form::number('pay_term_number', null, ['class' => 'form-control width-40 pull-left', 'placeholder' => __('contact.pay_term')]); !!}
                        {!! Form::select('pay_term_type', 
                            ['months' => __('lang_v1.months'), 
                                'days' => __('lang_v1.days')], 
                                null, 
                            ['class' => 'form-control width-60 pull-left','placeholder' => __('messages.please_select'), 'id' => 'pay_term_type']); !!}
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('document', __('purchase.attach_document') . ':') !!}
                    {!! Form::file('document', ['id' => 'upload_document']); !!}
                    <p class="help-block">
                        @lang('purchase.max_file_size', ['size' => 5])
                    </p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('purchase_order_ids', __('lang_v1.purchase_order').':') !!}
                    <div class="input-group">
                        {!! Form::text('purchase_order_ref_display', (isset($purchase_order) && $purchase_order) ? $purchase_order->ref_no : '', ['class' => 'form-control', 'readonly', 'id' => 'purchase_order_ref_display']); !!}
                        {!! Form::hidden('purchase_order_ids[]', (isset($purchase_order) && $purchase_order) ? $purchase_order->id : '', ['id' => 'purchase_order_ids']); !!}
                    </div>
                </div>
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-12">
                <div class="table-responsive">
                    <table class="table table-condensed table-bordered table-th-green text-center table-striped" id="stock_receive_table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('product.product_name')</th>
                                <th>@lang('purchase.purchase_quantity')</th>
                                <th>@lang('product.unit')</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <hr/>
                <div class="pull-right col-md-5">
                    <table class="pull-right col-md-12">
                        <tr>
                            <th class="col-md-7 text-right">@lang('lang_v1.total_items'):</th>
                            <td class="col-md-5 text-left">
                                <span id="total_quantity" class="display_currency" data-currency_symbol="false">0</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <input type="hidden" id="row_count" value="0">
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 text-left">
                <a href="{{action([\App\Http\Controllers\PurchaseStockReceiveController::class, 'index'])}}" class="btn btn-default">@lang('messages.cancel')</a>
                <button type="submit" class="btn btn-primary">@lang('Receive Stock')</button>
            </div>
        </div>
    @endcomponent

    {!! Form::close() !!}
</section>

<!-- quick product modal -->
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    @include('contact.create', ['quick_add' => true])
</div>

@endsection

@section('javascript')
<script src="{{ asset('js/purchase.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
<style>
/* Hide number input spinners */
input.quantity-input::-webkit-outer-spin-button,
input.quantity-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input.quantity-input[type=number] {
    -moz-appearance: textfield;
}
</style>
<script type="text/javascript">
$(document).ready(function(){
    var row_count = 0;
    
    // Initialize date picker
    if (typeof $.fn.datetimepicker !== 'undefined') {
        $('#transaction_date').datetimepicker({
            format: moment_date_format,
            ignoreReadonly: true,
        });
    }

    // Auto-load purchase orders and details if purchase order is pre-selected
    @if(isset($purchase_order) && $purchase_order)
        loadPurchaseOrderDetails({{ $purchase_order->id }});
    @endif

    // When supplier changes, load purchase orders
    $('#supplier_id').change(function(){
        var contact_id = $(this).val();
        if(contact_id) {
            loadSupplierAddress($(this));
        } else {
            $('#supplier_address_div').html('');
        }
    });

    function loadSupplierAddress($supplier_select) {
        var supplier_info = $supplier_select.find(':selected').data();
        $('#supplier_address_div').html('');
        var html = '';
        if(supplier_info && supplier_info.supplier_business_name){
            html += supplier_info.supplier_business_name + '<br>';
        }
        if(supplier_info && supplier_info.address_line_1){
            html += supplier_info.address_line_1 + '<br>';
        }
        if(supplier_info && supplier_info.address_line_2){
            html += supplier_info.address_line_2 + '<br>';
        }
        if(supplier_info && supplier_info.city){
            html += supplier_info.city + ', ';
        }
        if(supplier_info && supplier_info.state){
            html += supplier_info.state + ', ';
        }
        if(supplier_info && supplier_info.country){
            html += supplier_info.country + ' ';
        }
        if(supplier_info && supplier_info.zip_code){
            html += supplier_info.zip_code;
        }
        $('#supplier_address_div').html(html);
    }

    function loadPurchaseOrderDetails(purchase_order_id) {
        $.ajax({
            method: 'GET',
            url: '{{url("purchase-stock-receive/get-purchase-order-details")}}/' + purchase_order_id,
            dataType: 'json',
            success: function(result) {
                $('#stock_receive_table tbody').empty();
                row_count = 0;
                
                var products = result.products || result;
                $.each(products, function(i, product) {
                    addProductRow(product);
                });
                
                updateTotalQuantity();
            },
            error: function(xhr, status, error) {
                console.log('Error loading purchase order details:', error);
            }
        });
    }

    function addProductRow(product) {
        row_count++;
        var multiplier = parseFloat(product.base_unit_multiplier) || 1;
        var max_qty_base = parseFloat(product.quantity_remaining) || 0;
        var max_qty_display = max_qty_base / multiplier;
        var receive_qty = parseFloat(product.purchase_quantity) || 0;
        var unit_name = product.unit_name || '';
        
        var row = '<tr data-row-index="' + row_count + '">';
        row += '<td>' + row_count + '</td>';
        row += '<td>' + product.product_name + '</td>';
        row += '<td>';
        row += '<input type="text" class="form-control quantity-input" ';
        row += 'value="' + receive_qty + '" ';
        row += 'data-max-qty="' + max_qty_display + '" ';
        row += 'data-multiplier="' + multiplier + '" ';
        row += 'style="width: 120px; margin: 0 auto; text-align: center;">';
        row += '</td>';
        row += '<td style="text-align: center;">' + unit_name + '</td>';
        
        row += '<input type="hidden" name="products[' + row_count + '][quantity]" class="quantity-hidden" value="' + receive_qty + '">';
        row += '<input type="hidden" name="products[' + row_count + '][product_id]" value="' + product.product_id + '">';
        row += '<input type="hidden" name="products[' + row_count + '][variation_id]" value="' + product.variation_id + '">';
        row += '<input type="hidden" name="products[' + row_count + '][purchase_price]" value="' + (product.purchase_price || 0) + '">';
        row += '<input type="hidden" name="products[' + row_count + '][purchase_price_inc_tax]" value="' + (product.purchase_price_inc_tax || 0) + '">';
        row += '<input type="hidden" name="products[' + row_count + '][pp_without_discount]" value="' + (product.pp_without_discount || 0) + '">';
        row += '<input type="hidden" name="products[' + row_count + '][discount_percent]" value="' + (product.discount_percent || 0) + '">';
        row += '<input type="hidden" name="products[' + row_count + '][item_tax]" value="' + (product.item_tax || 0) + '">';
        row += '<input type="hidden" name="products[' + row_count + '][tax_id]" value="' + (product.tax_id || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][purchase_order_line_id]" value="' + (product.purchase_order_line_id || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][lot_number]" value="' + (product.lot_number || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][mfg_date]" value="' + (product.mfg_date || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][exp_date]" value="' + (product.exp_date || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][sub_unit_id]" value="' + (product.sub_unit_id || '') + '">';
        row += '<input type="hidden" name="products[' + row_count + '][product_unit_id]" value="' + (product.product_unit_id || '') + '">';
        
        row += '</tr>';
        
        $('#stock_receive_table tbody').append(row);
    }

    $(document).on('input change', '.quantity-input', function() {
        var $input = $(this);
        var value = parseFloat($input.val()) || 0;
        var max = parseFloat($input.attr('data-max-qty')) || 0;
        
        if (value > max) {
            $input.val(max);
            value = max;
            if (typeof toastr !== 'undefined') {
                toastr.warning('Quantity cannot exceed remaining quantity of ' + max);
            }
        }
        
        if (value < 0) {
            $input.val(0);
            value = 0;
        }
        
        var $row = $input.closest('tr');
        $row.find('.quantity-hidden').val(value);
        
        updateTotalQuantity();
    });

    function updateTotalQuantity() {
        var total = 0;
        $('.quantity-input').each(function() {
            var qty = parseFloat($(this).val()) || 0;
            total += qty;
        });
        $('#total_quantity').text(total.toFixed(2));
    }

    $('#stock_receive_form').on('submit', function(e) {
        e.preventDefault();
        
        if($('#stock_receive_table tbody tr').length === 0) {
            if (typeof toastr !== 'undefined') {
                toastr.error('@lang("lang_v1.please_add_products")');
            } else {
                alert('@lang("lang_v1.please_add_products")');
            }
            return false;
        }
        
        var hasError = false;
        $('.quantity-input').each(function() {
            var $input = $(this);
            var value = parseFloat($input.val()) || 0;
            var max = parseFloat($input.attr('data-max-qty')) || 0;
            
            if (value > max) {
                hasError = true;
                $input.addClass('error').css('border-color', 'red');
            } else {
                $input.removeClass('error').css('border-color', '');
            }
        });
        
        if (hasError) {
            if (typeof toastr !== 'undefined') {
                toastr.error('Please correct quantity errors before submitting');
            }
            return false;
        }
        
        var refNo = $('input[name="ref_no"]').val();
        if (!refNo || refNo.trim() === '') {
            showRefNoConfirmationDialog();
        } else {
            submitForm();
        }
    });

    function showRefNoConfirmationDialog() {
        var modalHtml = `
            <div class="modal fade" id="refNoConfirmModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">
                                <span>×</span>
                            </button>
                            <h4 class="modal-title">Confirmation</h4>
                        </div>
                        <div class="modal-body">
                            <p>Reference No. is not filled. Are you sure you want to receive stock?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" id="cancelRefNo">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmRefNoSubmit">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#refNoConfirmModal').remove();
        $('body').append(modalHtml);
        $('#refNoConfirmModal').modal('show');
        
        $('#confirmRefNoSubmit').on('click', function() {
            $('#refNoConfirmModal').modal('hide');
            setTimeout(function() {
                submitForm();
            }, 300);
        });
        
        $('#cancelRefNo').on('click', function() {
            $('#refNoConfirmModal').modal('hide');
            // Ensure the "Receive Stock" button remains enabled
            $('button[type="submit"]').prop('disabled', false).removeClass('disabled');
        });
        
        $('#refNoConfirmModal').on('hidden.bs.modal', function() {
            $('#refNoConfirmModal').remove();
        });
    }

    function submitForm() {
        var $form = $('#stock_receive_form');
        $form.unbind('submit').submit();
    }

    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2();
    }
});

// Fallback if document ready doesn't work
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded. Please ensure jQuery is included before this script.');
}
</script>
@endsection