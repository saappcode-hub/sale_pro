@extends('layouts.app')
@section('title', __('sale.products'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('sale.products')
        <small>@lang('lang_v1.manage_products')</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
<div class="row">
    <div class="col-md-12">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('type', __('product.product_type') . ':') !!}
                {!! Form::select('type', ['single' => __('lang_v1.single'), 'variable' => __('lang_v1.variable'), 'combo' => __('lang_v1.combo'), 'combo_single' => __('Combo Single')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_list_filter_type', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('category_id', __('product.category') . ':') !!}
                {!! Form::select('category_id', $categories, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_list_filter_category_id', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('unit_id', __('product.unit') . ':') !!}
                {!! Form::select('unit_id', $units, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_list_filter_unit_id', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('tax_id', __('product.tax') . ':') !!}
                {!! Form::select('tax_id', $taxes, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_list_filter_tax_id', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('brand_id', __('product.brand') . ':') !!}
                {!! Form::select('brand_id', $brands, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_list_filter_brand_id', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3" id="location_filter">
            <div class="form-group">
                {!! Form::label('location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id'=> 'location_id', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('group_product',  __('Product Type2') . ':') !!}
                {!! Form::select('group_product', ['1' => __('Normal'), '2' => __('WholeSale')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'group_product', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('group_price', __('Group Price') . ':') !!}
                {!! Form::select('group_price', $price_groups, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'group_price', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <br>
            <div class="form-group">
                {!! Form::select('active_state', ['active' => __('business.is_active'), 'inactive' => __('lang_v1.inactive')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'active_state', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        @if($is_admin)
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('product_kpi',  __('Product Count KPI') . ':') !!}
                    {!! Form::select('product_kpi', ['1' => __('Product Not Count KPI'), '2' => __('Product Count KPI')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_kpi', 'placeholder' => __('lang_v1.all')]); !!}
                </div>
            </div>
        @endif

        <!-- include module filter -->
        @if(!empty($pos_module_data))
            @foreach($pos_module_data as $key => $value)
                @if(!empty($value['view_path']))
                    @includeIf($value['view_path'], ['view_data' => $value['view_data']])
                @endif
            @endforeach
        @endif

        <div class="col-md-3">
          <div class="form-group">
            <br>
            <label>
              {!! Form::checkbox('not_for_selling', 1, false, ['class' => 'input-icheck', 'id' => 'not_for_selling']); !!} <strong>@lang('lang_v1.not_for_selling')</strong>
            </label>
          </div>
        </div>

        @if($is_woocommerce)
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <label>
                      {!! Form::checkbox('woocommerce_enabled', 1, false, 
                      [ 'class' => 'input-icheck', 'id' => 'woocommerce_enabled']); !!} {{ __('lang_v1.woocommerce_enabled') }}
                    </label>
                </div>
            </div>
        @endif
        
    @endcomponent
    </div>
</div>
@can('product.view')
    <div class="row">
        <div class="col-md-12">
           <!-- Custom Tabs -->
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#product_list_tab" data-toggle="tab" aria-expanded="true"><i class="fa fa-cubes" aria-hidden="true"></i> @lang('lang_v1.all_products')</a>
                    </li>
                    @can('stock_report.view')
                    <li>
                        <a href="#product_stock_report" data-toggle="tab" aria-expanded="true"><i class="fa fa-hourglass-half" aria-hidden="true"></i> @lang('report.stock_report')</a>
                    </li>
                    @endcan
                </ul>

                <div class="tab-content">
                    <div class="tab-pane active" id="product_list_tab">
                        @can('product.view')
                            <button class="btn btn-warning pull-right margin-left-10" id="refresh-stock-btn">
                                <i class="fa fa-refresh"></i> Refresh Stock
                            </button>
                        @endcan
                        @if($is_admin)
                            <a class="btn btn-success pull-right margin-left-10" href="{{action([\App\Http\Controllers\ProductController::class, 'downloadExcel'])}}"><i class="fa fa-download"></i> @lang('lang_v1.download_excel')</a>
                        @endif
                        @can('product.create')                            
                            <a class="btn btn-primary pull-right" href="{{action([\App\Http\Controllers\ProductController::class, 'create'])}}">
                                        <i class="fa fa-plus"></i> @lang('messages.add')</a>
                            <br><br>
                        @endcan
                        @include('product.partials.product_list')
                    </div>
                    @can('stock_report.view')
                    <div class="tab-pane" id="product_stock_report">
                        @include('report.partials.stock_report_table')
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endcan
<input type="hidden" id="is_rack_enabled" value="{{$rack_enabled}}">

<div class="modal fade product_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade" id="view_product_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade" id="opening_stock_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

@if($is_woocommerce)
    @include('product.partials.toggle_woocommerce_sync_modal')
@endif
@include('product.partials.edit_product_location_modal')

<div class="modal fade" id="stock-refresh-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fa fa-refresh fa-spin"></i> Refreshing Stock Data
                </h4>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <p id="progress-message">Initializing stock refresh...</p>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped active" 
                             role="progressbar" 
                             aria-valuenow="0" 
                             aria-valuemin="0" 
                             aria-valuemax="100" 
                             style="width: 0%; min-width: 2em;">
                            <span id="progress-percentage">0%</span>
                        </div>
                    </div>
                    <p class="text-muted" id="progress-details">
                        <small>Please wait while we synchronize your stock data...</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

</section>
<!-- /.content -->

@endsection

@section('javascript')
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        $(document).ready( function(){
            product_table = $('#product_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[3, 'asc']],
        scrollY: "75vh",
        scrollX: true,
        scrollCollapse: true,
        "ajax": {
            "url": "/products",
            "data": function ( d ) {
                d.type = $('#product_list_filter_type').val();
                d.group_price = $('#group_price').val(); // Fixed: was groupPrice
                d.group_product = $('#group_product').val();
                d.category_id = $('#product_list_filter_category_id').val();
                d.brand_id = $('#product_list_filter_brand_id').val();
                d.unit_id = $('#product_list_filter_unit_id').val();
                d.tax_id = $('#product_list_filter_tax_id').val();
                d.active_state = $('#active_state').val();
                d.not_for_selling = $('#not_for_selling').is(':checked');
                d.location_id = $('#location_id').val();
                d.product_kpi = $('#product_kpi').val();
                
                if ($('#repair_model_id').length == 1) {
                    d.repair_model_id = $('#repair_model_id').val();
                }

                if ($('#woocommerce_enabled').length == 1 && $('#woocommerce_enabled').is(':checked')) {
                    d.woocommerce_enabled = 1;
                }

                d = __datatable_ajax_callback(d);
                
                // Debug logging
                console.log('DataTable request data:', d);
                return d;
            },
            "error": function(xhr, error, thrown) {
                console.error('DataTables Ajax error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Status:', xhr.status);
                
                // Show user-friendly error
                toastr.error('Error loading data. Please check console for details.');
                
                // Try to parse error response
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        console.error('Server error:', response.error);
                        toastr.error('Server error: ' + response.error);
                    }
                } catch (e) {
                    console.error('Could not parse error response');
                }
            }
        },
        columnDefs: [ {
            "targets": [0, 1, 2],
            "orderable": false,
            "searchable": false
        } ],
        columns: [
            { data: 'mass_delete' },
            { data: 'image', name: 'products.image' },
            { data: 'action', name: 'action'},
            { data: 'product', name: 'products.name' },
            { data: 'product_locations', name: 'product_locations' },
            @can('view_purchase_price')
                { data: 'purchase_price', name: 'max_purchase_price', searchable: false},
            @endcan
            @can('access_default_selling_price')
                { data: 'selling_price', name: 'max_price', searchable: false},
            @endcan
            { data: 'current_stock', searchable: false},
            { data: 'quantity', name: 'quantity'},
            { data: 'type', name: 'products.type'},
            { data: 'group_product', name: 'group_product'},
            { data: 'group_prices', name: 'group_prices'},
            @if($is_admin)
                { data: 'product_kpi', name: 'products.product_kpi'},
            @endif
            { data: 'category', name: 'c1.name'},
            { data: 'brand', name: 'brands.name'},
            { data: 'tax', name: 'tax_rates.name', searchable: false},
            { data: 'sku', name: 'products.sku'},
            { data: 'purchase_code', name: 'purchase_code'},
            { data: 'product_custom_field1', name: 'products.product_custom_field1', visible: $('#cf_1').text().length > 0 },
            { data: 'product_custom_field2', name: 'products.product_custom_field2', visible: $('#cf_2').text().length > 0},
            { data: 'product_custom_field3', name: 'products.product_custom_field3', visible: $('#cf_3').text().length > 0},
            { data: 'product_custom_field4', name: 'products.product_custom_field4', visible: $('#cf_4').text().length > 0 },
            { data: 'product_custom_field5', name: 'products.product_custom_field5', visible: $('#cf_5').text().length > 0 },
            { data: 'product_custom_field6', name: 'products.product_custom_field6', visible: $('#cf_6').text().length > 0 },
            { data: 'product_custom_field7', name: 'products.product_custom_field7', visible: $('#cf_7').text().length > 0 },
        ],
        createdRow: function( row, data, dataIndex ) {
            if($('input#is_rack_enabled').val() == 1){
                var target_col = 0;
                @can('product.delete')
                    target_col = 1;
                @endcan
                $( row ).find('td:eq('+target_col+') div').prepend('<i style="margin:auto;" class="fa fa-plus-circle text-success cursor-pointer no-print rack-details" title="' + LANG.details + '"></i>&nbsp;&nbsp;');
            }
            $( row ).find('td:eq(0)').attr('class', 'selectable_td');
        },
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#product_table'));
        },
    });
            // Array to track the ids of the details displayed rows
            var detailRows = [];

            $('#product_table tbody').on( 'click', 'tr i.rack-details', function () {
                var i = $(this);
                var tr = $(this).closest('tr');
                var row = product_table.row( tr );
                var idx = $.inArray( tr.attr('id'), detailRows );

                if ( row.child.isShown() ) {
                    i.addClass( 'fa-plus-circle text-success' );
                    i.removeClass( 'fa-minus-circle text-danger' );

                    row.child.hide();
         
                    // Remove from the 'open' array
                    detailRows.splice( idx, 1 );
                } else {
                    i.removeClass( 'fa-plus-circle text-success' );
                    i.addClass( 'fa-minus-circle text-danger' );

                    row.child( get_product_details( row.data() ) ).show();
         
                    // Add to the 'open' array
                    if ( idx === -1 ) {
                        detailRows.push( tr.attr('id') );
                    }
                }
            });

            $('#opening_stock_modal').on('hidden.bs.modal', function(e) {
                product_table.ajax.reload();
            });

            $('table#product_table tbody').on('click', 'a.delete-product', function(e){
                e.preventDefault();
                swal({
                  title: LANG.sure,
                  icon: "warning",
                  buttons: true,
                  dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        var href = $(this).attr('href');
                        $.ajax({
                            method: "DELETE",
                            url: href,
                            dataType: "json",
                            success: function(result){
                                if(result.success == true){
                                    toastr.success(result.msg);
                                    product_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });

            $(document).on('click', '#delete-selected', function(e){
                e.preventDefault();
                var selected_rows = getSelectedRows();
                
                if(selected_rows.length > 0){
                    $('input#selected_rows').val(selected_rows);
                    swal({
                        title: LANG.sure,
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((willDelete) => {
                        if (willDelete) {
                            $('form#mass_delete_form').submit();
                        }
                    });
                } else{
                    $('input#selected_rows').val('');
                    swal('@lang("lang_v1.no_row_selected")');
                }    
            });

            $(document).on('click', '#deactivate-selected', function(e){
                e.preventDefault();
                var selected_rows = getSelectedRows();
                
                if(selected_rows.length > 0){
                    $('input#selected_products').val(selected_rows);
                    swal({
                        title: LANG.sure,
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((willDelete) => {
                        if (willDelete) {
                            var form = $('form#mass_deactivate_form')

                            var data = form.serialize();
                                $.ajax({
                                    method: form.attr('method'),
                                    url: form.attr('action'),
                                    dataType: 'json',
                                    data: data,
                                    success: function(result) {
                                        if (result.success == true) {
                                            toastr.success(result.msg);
                                            product_table.ajax.reload();
                                            form
                                            .find('#selected_products')
                                            .val('');
                                        } else {
                                            toastr.error(result.msg);
                                        }
                                    },
                                });
                        }
                    });
                } else{
                    $('input#selected_products').val('');
                    swal('@lang("lang_v1.no_row_selected")');
                }    
            })

            $(document).on('click', '#edit-selected', function(e){
                e.preventDefault();
                var selected_rows = getSelectedRows();
                
                if(selected_rows.length > 0){
                    $('input#selected_products_for_edit').val(selected_rows);
                    $('form#bulk_edit_form').submit();
                } else{
                    $('input#selected_products').val('');
                    swal('@lang("lang_v1.no_row_selected")');
                }    
            })

            $('table#product_table tbody').on('click', 'a.activate-product', function(e){
                e.preventDefault();
                var href = $(this).attr('href');
                $.ajax({
                    method: "get",
                    url: href,
                    dataType: "json",
                    success: function(result){
                        if(result.success == true){
                            toastr.success(result.msg);
                            product_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

               $(document).on('change', '#product_list_filter_type, #product_list_filter_category_id, #product_list_filter_brand_id, #product_list_filter_unit_id, #product_list_filter_tax_id, #group_product, #group_price, #location_id, #active_state, #product_kpi, #repair_model_id', 
                    function() {
                        console.log('Filter changed:', $(this).attr('id'), $(this).val());
                        
                        if ($("#product_list_tab").hasClass('active')) {
                            product_table.ajax.reload();
                        }

                        if ($("#product_stock_report").hasClass('active')) {
                            stock_report_table.ajax.reload();
                        }
                });

                $(document).on('ifChanged', '#not_for_selling, #woocommerce_enabled', function(){
                    console.log('Checkbox changed:', $(this).attr('id'), $(this).is(':checked'));
                    
                    if ($("#product_list_tab").hasClass('active')) {
                        product_table.ajax.reload();
                    }

                    if ($("#product_stock_report").hasClass('active')) {
                        stock_report_table.ajax.reload();
                    }
                });

            $('#product_location').select2({dropdownParent: $('#product_location').closest('.modal')});

            @if($is_woocommerce)
                $(document).on('click', '.toggle_woocomerce_sync', function(e){
                    e.preventDefault();
                    var selected_rows = getSelectedRows();
                    if(selected_rows.length > 0){
                        $('#woocommerce_sync_modal').modal('show');
                        $("input#woocommerce_products_sync").val(selected_rows);
                    } else{
                        $('input#selected_products').val('');
                        swal('@lang("lang_v1.no_row_selected")');
                    }    
                });

                $(document).on('submit', 'form#toggle_woocommerce_sync_form', function(e){
                    e.preventDefault();
                    var url = $('form#toggle_woocommerce_sync_form').attr('action');
                    var method = $('form#toggle_woocommerce_sync_form').attr('method');
                    var data = $('form#toggle_woocommerce_sync_form').serialize();
                    var ladda = Ladda.create(document.querySelector('.ladda-button'));
                    ladda.start();
                    $.ajax({
                        method: method,
                        dataType: "json",
                        url: url,
                        data:data,
                        success: function(result){
                            ladda.stop();
                            if (result.success) {
                                $("input#woocommerce_products_sync").val('');
                                $('#woocommerce_sync_modal').modal('hide');
                                toastr.success(result.msg);
                                product_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                });
            @endif
        });

        $(document).on('shown.bs.modal', 'div.view_product_modal, div.view_modal, #view_product_modal',
            function(){
                var div = $(this).find('#view_product_stock_details');
            if (div.length) {
                $.ajax({
                    url: "{{action([\App\Http\Controllers\ReportController::class, 'getStockReport'])}}"  + '?for=view_product&product_id=' + div.data('product_id'),
                    dataType: 'html',
                    success: function(result) {
                        div.html(result);
                        __currency_convert_recursively(div);
                    },
                });
            }
            __currency_convert_recursively($(this));
        });
        var data_table_initailized = false;
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if ($(e.target).attr('href') == '#product_stock_report') {
                if (!data_table_initailized) {
                    //Stock report table
                    var stock_report_cols = [
                        { data: 'action', name: 'action', searchable: false, orderable: false },
                        { data: 'sku', name: 'variations.sub_sku' },
                        { data: 'product', name: 'p.name' },
                        { data: 'variation', name: 'variation' },
                        { data: 'category_name', name: 'c.name' },
                        { data: 'location_name', name: 'l.name' },
                        { data: 'unit_price', name: 'variations.sell_price_inc_tax' },
                        { data: 'stock', name: 'stock', searchable: false },
                        { data: 'quantity', name: 'quantity'},
                    ];
                    if ($('th.stock_price').length) {
                        stock_report_cols.push({ data: 'stock_price', name: 'stock_price', searchable: false });
                        stock_report_cols.push({ data: 'stock_value_by_sale_price', name: 'stock_value_by_sale_price', searchable: false, orderable: false });
                        stock_report_cols.push({ data: 'potential_profit', name: 'potential_profit', searchable: false, orderable: false });
                    }

                    stock_report_cols.push({ data: 'total_sold', name: 'total_sold', searchable: false });
                    stock_report_cols.push({ data: 'total_transfered', name: 'total_transfered', searchable: false });
                    stock_report_cols.push({ data: 'total_adjusted', name: 'total_adjusted', searchable: false });
                    stock_report_cols.push({ data: 'product_custom_field1', name: 'p.product_custom_field1'});
                    stock_report_cols.push({ data: 'product_custom_field2', name: 'p.product_custom_field2'});
                    stock_report_cols.push({ data: 'product_custom_field3', name: 'p.product_custom_field3'});
                    stock_report_cols.push({ data: 'product_custom_field4', name: 'p.product_custom_field4'});

                    if ($('th.current_stock_mfg').length) {
                        stock_report_cols.push({ data: 'total_mfg_stock', name: 'total_mfg_stock', searchable: false });
                    }
                    stock_report_table = $('#stock_report_table').DataTable({
                        order: [[1, 'asc']],
                        processing: true,
                        serverSide: true,
                        scrollY: "75vh",
                        scrollX:        true,
                        scrollCollapse: true,
                        ajax: {
                            url: '/reports/stock-report',
                            data: function(d) {
                                d.location_id = $('#location_id').val();
                                d.category_id = $('#product_list_filter_category_id').val();
                                d.brand_id = $('#product_list_filter_brand_id').val();
                                d.unit_id = $('#product_list_filter_unit_id').val();
                                d.type = $('#product_list_filter_type').val();
                                d.active_state = $('#active_state').val();
                                d.not_for_selling = $('#not_for_selling').is(':checked');
                                if ($('#repair_model_id').length == 1) {
                                    d.repair_model_id = $('#repair_model_id').val();
                                }
                            }
                        },
                        columns: stock_report_cols,
                        fnDrawCallback: function(oSettings) {
                            __currency_convert_recursively($('#stock_report_table'));
                        },
                        "footerCallback": function ( row, data, start, end, display ) {
                            var footer_total_stock = 0;
                            var footer_total_sold = 0;
                            var footer_total_transfered = 0;
                            var total_adjusted = 0;
                            var total_stock_price = 0;
                            var footer_stock_value_by_sale_price = 0;
                            var total_potential_profit = 0;
                            var footer_total_mfg_stock = 0;
                            for (var r in data){
                                footer_total_stock += $(data[r].stock).data('orig-value') ? 
                                parseFloat($(data[r].stock).data('orig-value')) : 0;

                                footer_total_sold += $(data[r].total_sold).data('orig-value') ? 
                                parseFloat($(data[r].total_sold).data('orig-value')) : 0;

                                footer_total_transfered += $(data[r].total_transfered).data('orig-value') ? 
                                parseFloat($(data[r].total_transfered).data('orig-value')) : 0;

                                total_adjusted += $(data[r].total_adjusted).data('orig-value') ? 
                                parseFloat($(data[r].total_adjusted).data('orig-value')) : 0;

                                total_stock_price += $(data[r].stock_price).data('orig-value') ? 
                                parseFloat($(data[r].stock_price).data('orig-value')) : 0;

                                footer_stock_value_by_sale_price += $(data[r].stock_value_by_sale_price).data('orig-value') ? 
                                parseFloat($(data[r].stock_value_by_sale_price).data('orig-value')) : 0;

                                total_potential_profit += $(data[r].potential_profit).data('orig-value') ? 
                                parseFloat($(data[r].potential_profit).data('orig-value')) : 0;

                                footer_total_mfg_stock += $(data[r].total_mfg_stock).data('orig-value') ? 
                                parseFloat($(data[r].total_mfg_stock).data('orig-value')) : 0;
                            }

                            $('.footer_total_stock').html(__currency_trans_from_en(footer_total_stock, false));
                            $('.footer_total_stock_price').html(__currency_trans_from_en(total_stock_price));
                            $('.footer_total_sold').html(__currency_trans_from_en(footer_total_sold, false));
                            $('.footer_total_transfered').html(__currency_trans_from_en(footer_total_transfered, false));
                            $('.footer_total_adjusted').html(__currency_trans_from_en(total_adjusted, false));
                            $('.footer_stock_value_by_sale_price').html(__currency_trans_from_en(footer_stock_value_by_sale_price));
                            $('.footer_potential_profit').html(__currency_trans_from_en(total_potential_profit));
                            if ($('th.current_stock_mfg').length) {
                                $('.footer_total_mfg_stock').html(__currency_trans_from_en(footer_total_mfg_stock, false));
                            }
                        },
                                    });
                    data_table_initailized = true;
                } else {
                    stock_report_table.ajax.reload();
                }
            } else {
                product_table.ajax.reload();
            }
        });

        $(document).on('click', '.update_product_location', function(e){
            e.preventDefault();
            var selected_rows = getSelectedRows();
            
            if(selected_rows.length > 0){
                $('input#selected_products').val(selected_rows);
                var type = $(this).data('type');
                var modal = $('#edit_product_location_modal');
                if(type == 'add') {
                    modal.find('.remove_from_location_title').addClass('hide');
                    modal.find('.add_to_location_title').removeClass('hide');
                } else if(type == 'remove') {
                    modal.find('.add_to_location_title').addClass('hide');
                    modal.find('.remove_from_location_title').removeClass('hide');
                }

                modal.modal('show');
                modal.find('#product_location').select2({ dropdownParent: modal });
                modal.find('#product_location').val('').change();
                modal.find('#update_type').val(type);
                modal.find('#products_to_update_location').val(selected_rows);
            } else{
                $('input#selected_products').val('');
                swal('@lang("lang_v1.no_row_selected")');
            }    
        });

        // Replace the previous .update_product_kpi click handler with this updated version
        $(document).on('click', '.update_product_kpi', function(e){
            e.preventDefault();
            var selected_rows = getSelectedRows();
            var type = $(this).data('type');
            var kpi_value = (type === 'count_kpi') ? 2 : 1;
            var action_text = (type === 'count_kpi') ? 'set Product Count KPI' : 'remove Product KPI';
            
            if(selected_rows.length > 0){
                swal({
                    title: "Are you sure?",
                    text: "This will " + action_text + " for selected products",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((willUpdate) => {
                    if (willUpdate) {
                        $.ajax({
                            method: "POST",
                            url: "/products/update-kpi",
                            dataType: "json",
                            data: {
                                selected_products: selected_rows,
                                product_kpi: kpi_value,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(result){
                                if(result.success == true){
                                    toastr.success(result.msg);
                                    product_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            },
                            error: function(xhr, status, error) {
                                toastr.error("An error occurred while updating KPI");
                            }
                        });
                    }
                });
            } else {
                swal('@lang("lang_v1.no_row_selected")');
            }
        });

        $(document).on('submit', 'form#edit_product_location_form', function(e) {
            e.preventDefault();
            var form = $(this);
            var data = form.serialize();

            $.ajax({
                method: $(this).attr('method'),
                url: $(this).attr('action'),
                dataType: 'json',
                data: data,
                beforeSend: function(xhr) {
                    __disable_submit_button(form.find('button[type="submit"]'));
                },
                success: function(result) {
                    if (result.success == true) {
                        $('div#edit_product_location_modal').modal('hide');
                        toastr.success(result.msg);
                        product_table.ajax.reload();
                        $('form#edit_product_location_form')
                        .find('button[type="submit"]')
                        .attr('disabled', false);
                    } else {
                        toastr.error(result.msg);
                    }
                },
            });
        });

        // Enhanced stock refresh functionality
        $(document).on('click', '#refresh-stock-btn', function(e) {
            e.preventDefault();
            
            // Check if already refreshing
            if ($(this).hasClass('refreshing')) {
                return;
            }
            
            swal({
                title: "Refresh Stock Data?",
                text: "This will check and update all product stock quantities. This process may take several minutes depending on your inventory size.",
                icon: "warning",
                buttons: ["Cancel", "Yes, Refresh"],
                dangerMode: false,
            }).then((willRefresh) => {
                if (willRefresh) {
                    startStockRefresh();
                }
            });
        });

        function startStockRefresh() {
            // Mark button as refreshing and disable it
            const refreshBtn = $('#refresh-stock-btn');
            refreshBtn.addClass('refreshing').prop('disabled', true);
            refreshBtn.html('<i class="fa fa-spinner fa-spin"></i> Refreshing...');
            
            // Show progress modal
            $('#stock-refresh-modal').modal('show');
            
            // Disable all interactive elements to prevent user actions
            disableUserInteraction();
            
            // Reset progress
            updateProgress(0, 'Initializing stock refresh...', 'Please wait while we synchronize your stock data...');
            
            const startTime = Date.now();
            
            // Start the refresh process
            $.ajax({
                url: "{{ route('products.refresh-stock-data') }}",
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                timeout: 900000, // 15 minutes timeout
                success: function(response) {
                    const endTime = Date.now();
                    const duration = Math.round((endTime - startTime) / 1000);
                    
                    if (response.success) {
                        updateProgress(100, 'Stock refresh completed!', 
                            `${response.total_updated} records updated, ${response.total_skipped || 0} skipped out of ${response.total_checked} checked in ${duration}s.`);
                        
                        // Show success message with details
                        let successMessage = response.message;
                        if (response.total_skipped > 0) {
                            successMessage += ` (${response.total_skipped} products already had correct stock)`;
                        }
                        
                        setTimeout(function() {
                            $('#stock-refresh-modal').modal('hide');
                            toastr.success(successMessage);
                            
                            // Reload the product table
                            if (typeof product_table !== 'undefined') {
                                product_table.ajax.reload();
                            }
                            
                            // Reload stock report table if active
                            if ($("#product_stock_report").hasClass('active') && typeof stock_report_table !== 'undefined') {
                                stock_report_table.ajax.reload();
                            }
                        }, 2000);
                    } else {
                        handleRefreshError(response.message || 'Unknown error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Connection error occurred';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMessage = response.message || errorMessage;
                    } catch (e) {
                        if (status === 'timeout') {
                            errorMessage = 'Process timed out - please try refreshing a smaller subset of products';
                        } else if (xhr.status === 403) {
                            errorMessage = 'You do not have permission to refresh stock data';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error occurred - please try again later';
                        }
                    }
                    handleRefreshError(errorMessage);
                },
                complete: function() {
                    // Re-enable user interaction
                    enableUserInteraction();
                    
                    // Reset refresh button
                    const refreshBtn = $('#refresh-stock-btn');
                    refreshBtn.removeClass('refreshing').prop('disabled', false);
                    refreshBtn.html('<i class="fa fa-refresh"></i> Refresh Data');
                }
            });
        }

        function disableUserInteraction() {
            // Disable all buttons except the close button in modals
            $('button, input[type="submit"], a.btn').not('#stock-refresh-modal button').prop('disabled', true);
            
            // Disable all form inputs
            $('input, select, textarea').not('#stock-refresh-modal input, #stock-refresh-modal select').prop('disabled', true);
            
            // Add a class to body to prevent other interactions
            $('body').addClass('stock-refreshing');
            
            // Add overlay to prevent clicks
            if (!$('#refresh-overlay').length) {
                $('body').append('<div id="refresh-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.1); z-index: 999; pointer-events: none;"></div>');
            }
        }

        function enableUserInteraction() {
            // Re-enable all buttons and form elements
            $('button, input, select, textarea').prop('disabled', false);
            
            // Remove the body class
            $('body').removeClass('stock-refreshing');
            
            // Remove overlay
            $('#refresh-overlay').remove();
        }

        function updateProgress(percentage, message, details) {
            const progressBar = $('.progress-bar');
            const progressText = $('#progress-percentage');
            const progressMessage = $('#progress-message');
            const progressDetails = $('#progress-details small');
            
            // Ensure percentage is between 0 and 100
            percentage = Math.max(0, Math.min(100, percentage));
            
            progressBar.css('width', percentage + '%');
            progressBar.attr('aria-valuenow', percentage);
            progressText.text(Math.round(percentage) + '%');
            progressMessage.text(message);
            progressDetails.text(details);
            
            // Change color based on progress
            progressBar.removeClass('progress-bar-danger progress-bar-success progress-bar-warning');
            if (percentage === 100) {
                progressBar.addClass('progress-bar-success');
            } else if (percentage > 0) {
                progressBar.addClass('progress-bar-info');
            }
        }

        function handleRefreshError(errorMessage) {
            updateProgress(0, 'Error occurred!', errorMessage);
            $('.progress-bar').addClass('progress-bar-danger');
            $('.fa-refresh').removeClass('fa-spin');
            
            // Log error for debugging
            console.error('Stock refresh error:', errorMessage);
            
            setTimeout(function() {
                $('#stock-refresh-modal').modal('hide');
                toastr.error('Stock refresh failed: ' + errorMessage);
            }, 3000);
        }

        // Prevent modal from being closed during refresh
        $('#stock-refresh-modal').on('hide.bs.modal', function (e) {
            const progressValue = parseInt($('.progress-bar').attr('aria-valuenow') || 0);
            const hasError = $('.progress-bar').hasClass('progress-bar-danger');
            
            // Only allow closing if completed (100%) or has error
            if (progressValue < 100 && !hasError) {
                e.preventDefault();
                return false;
            }
        });

        // Add CSS for better UX during refresh
        if (!$('#refresh-stock-styles').length) {
            $('head').append(`
                <style id="refresh-stock-styles">
                .stock-refreshing {
                    cursor: wait !important;
                }
                .stock-refreshing * {
                    cursor: wait !important;
                }
                #refresh-stock-btn.refreshing {
                    cursor: wait !important;
                }
                .progress-bar-info {
                    background-color: #5bc0de;
                }
                </style>
            `);
        }
    </script>
@endsection
