@extends('layouts.app')
@section('title', __( 'lang_v1.sales_order'))
@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>@lang('lang_v1.sales_order')</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="box box-info collapsed-box" id="filters_box">
        <div class="box-header with-border" style="cursor: pointer;" id="filters_header">
            <h3 class="box-title">@lang('report.filters')</h3>
        </div>
        <div class="box-body" style="display: none;" id="filters_content">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sell_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                        {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all') ]); !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sell_list_filter_customer_id',  __('contact.customer') . ':') !!}
                        {!! Form::select('sell_list_filter_customer_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('so_list_filter_status',  __('sale.status') . ':') !!}
                        {!! Form::select('so_list_filter_status', $sales_order_statuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                    </div>
                </div>
                @if(!empty($shipping_statuses))
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('so_list_shipping_status', __('lang_v1.shipping_status') . ':') !!}
                            {!! Form::select('so_list_shipping_status', $shipping_statuses, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                        </div>
                    </div>
                @endif
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                        {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    @component('components.widget', ['class' => 'box-primary'])
        @can('so.create')
            @slot('tool')
                <div class="box-tools">
                    <a class="btn btn-block btn-primary" href="{{action([\App\Http\Controllers\SellController::class, 'create'])}}?sale_type=sales_order">
                    <i class="fa fa-plus"></i> @lang('lang_v1.add_sales_order')</a>
                </div>
            @endslot
        @endcan
        @if( auth()->user()->can('so.view_own') || auth()->user()->can('so.view_all'))
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="sell_table">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>@lang('restaurant.order_no')</th>
                        <th>@lang('sale.customer_name')</th>
                        <th>@lang('lang_v1.contact_no')</th>
                        <th>@lang('sale.location')</th>
                        <th>@lang('sale.status')</th>
                        <th>Delivery Person</th>
                        <th>@lang('lang_v1.shipping_status')</th>
                        <th>@lang('Quantity')</th>
                        <th>@lang('lang_v1.quantity_remaining')</th>
                        <th>@lang('KPI Quantity')</th>
                        <th>@lang('lang_v1.added_by')</th>
                    </tr>
                </thead>
            </table>
        </div>
        @endif
    @endcomponent
    <div class="modal fade edit_pso_status_modal" tabindex="-1" role="dialog"></div>
</section>

<!-- Sales Order Details Modal -->
<div class="modal fade" id="salesOrderDetailsModal" tabindex="-1" role="dialog"></div>

@stop

@section('javascript')
@includeIf('sales_order.common_js')
<script type="text/javascript">
$(document).ready(function(){
    // Status color mapping
    var statusColors = {
        'draft': '#6c757d',
        'partial': '#ff9800', 
        'ordered': '#2196f3',
        'completed': '#4caf50'
    };

    // Function to show sales order details popup
    function showSalesOrderDetails(transactionId) {
        $.ajax({
            url: '/sells/' + transactionId,
            method: 'GET',
            success: function(response) {
                $('#salesOrderDetailsModal').html(response);
                $('#salesOrderDetailsModal').modal('show');
                
                if (typeof __currency_convert_recursively === 'function') {
                    __currency_convert_recursively($('#salesOrderDetailsModal'));
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Failed to load sales order details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
            }
        });
    }

    // Function to show edit status modal
    function showEditStatusModal(transactionId) {
        $.ajax({
            url: '/edit-sales-orders/' + transactionId + '/status',
            method: 'GET',
            success: function(response) {
                $('.edit_pso_status_modal').html(response);
                $('.edit_pso_status_modal').modal('show');
                
                // Initialize select2 for the status dropdown
                $('#so_status').select2({
                    width: '100%'
                });

                // Initialize Ladda for the submit button
                var l = $('.ladda-button').ladda();
                
                // Handle form submission
                $('#update_so_status_form').on('submit', function(e) {
                    e.preventDefault();
                    l.ladda('start');
                    
                    $.ajax({
                        url: $(this).attr('action'),
                        method: 'PUT',
                        data: $(this).serialize(),
                        success: function(response) {
                            l.ladda('stop');
                            if (response.success) {
                                toastr.success(response.msg);
                                $('.edit_pso_status_modal').modal('hide');
                                sell_table.ajax.reload();
                            } else {
                                toastr.error(response.msg);
                            }
                        },
                        error: function(xhr) {
                            l.ladda('stop');
                            var errorMessage = 'Failed to update status.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            toastr.error(errorMessage);
                        }
                    });
                });
            },
            error: function(xhr) {
                var errorMessage = 'Failed to load status form.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
            }
        });
    }

    // Handle filter toggle by clicking on the header
    $('#filters_header').click(function() {
        var $filtersContent = $('#filters_content');
        var $filtersBox = $('#filters_box');
        
        if ($filtersContent.is(':visible')) {
            $filtersContent.slideUp();
            $filtersBox.addClass('collapsed-box');
        } else {
            $filtersContent.slideDown();
            $filtersBox.removeClass('collapsed-box');
        }
    });

    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            sell_table.ajax.reload();
        }
    );
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        sell_table.ajax.reload();
    });
    
    sell_table = $('#sell_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        "ajax": {
            "url": '/sells?sale_type=sales_order',
            "data": function ( d ) {
                if($('#sell_list_filter_date_range').val()) {
                    var start = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }
                if($('#sell_list_filter_location_id').length) {
                    d.location_id = $('#sell_list_filter_location_id').val();
                }
                d.customer_id = $('#sell_list_filter_customer_id').val();
                if ($('#so_list_filter_status').length) {
                    d.status = $('#so_list_filter_status').val();
                }
                if ($('#so_list_shipping_status').length) {
                    d.shipping_status = $('#so_list_shipping_status').val();
                }
                if($('#created_by').length) {
                    d.created_by = $('#created_by').val();
                }
            }
        },
        columnDefs: [ 
            {
                "targets": 8, // CHANGED: Shipping Status is now here (was 7)
                "orderable": false,
                "searchable": false
            },
            {
                // Custom rendering for status column (Index 6 - Unchanged)
                "targets": 6,
                "render": function(data, type, row) {
                    if (type === 'display') {
                        var statusText = $('<div>').html(data).text().toLowerCase().trim();
                        if (statusColors[statusText]) {
                            // Add clickable class and transaction ID only for partial and ordered
                            var isEditable = ['partial', 'ordered'].includes(statusText) && row.type === 'sales_order';
                            var className = isEditable ? 'edit-so-status' : '';
                            var dataAttr = isEditable ? ' data-transaction-id="' + row.id + '"' : '';
                            return '<span class="label ' + className + '" style="background-color: ' + statusColors[statusText] + ' !important; color: white !important; border: none !important; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; cursor: ' + (isEditable ? 'pointer' : 'default') + ';"' + dataAttr + '>' + 
                                   (statusText.charAt(0).toUpperCase() + statusText.slice(1)) + 
                                   '</span>';
                        }
                        return data;
                    }
                    return data;
                }
            },
            {
                // Format quantity columns with 2 decimal places
                // CHANGED: Targets 9, 10, 11 (Quantity, Remaining, KPI)
                // Delivery Person (7) and Shipping Status (8) are skipped
                "targets": [9, 10, 11],
                "render": function(data, type, row) {
                    // Added check to prevent NaN on empty strings/nulls
                    if (type === 'display' && data !== null && data !== '' && !isNaN(data)) {
                        return parseFloat(data).toFixed(2);
                    }
                    return data;
                }
            }
        ],
        columns: [
            { data: 'action', name: 'action' },
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'invoice_no', name: 'invoice_no' },
            { data: 'conatct_name', name: 'conatct_name' },
            { data: 'mobile', name: 'contacts.mobile' },
            { data: 'business_location', name: 'bl.name' },
            { data: 'sell_status', name: 'status' }, // Maps to sell_status for sales_order
            { data: 'delivery_person_name', name: 'dp.first_name', "searchable": false},
            { data: 'shipping_status', name: 'shipping_status' },
            { data: 'total_qty', name: 'total_qty', "searchable": false },
            { data: 'so_qty_remaining', name: 'so_qty_remaining', "searchable": false },
            { data: 'product_count_kpi', name: 'product_count_kpi', "searchable": false },
            { data: 'added_by', name: 'u.first_name' },
        ],
        createdRow: function(row, data, dataIndex) {
            $(row).attr('data-transaction-id', data.id);
            $(row).addClass('clickable-row');
        }
    });
    
    // Handle click on status column
    $('#sell_table tbody').on('click', 'td:nth-child(7) .edit-so-status', function(e) {
        e.stopPropagation(); // Prevent row click event
        var transactionId = $(this).data('transaction-id');
        if (transactionId) {
            showEditStatusModal(transactionId);
        }
    });

    // Row click handler for details
    $('#sell_table tbody').on('click', 'tr.clickable-row', function(e) {
        if ($(e.target).closest('.btn-group, .dropdown-menu, .dropdown-toggle, a, button, .edit-so-status').length) {
            return;
        }
        
        var transactionId = $(this).data('transaction-id');
        if (transactionId) {
            showSalesOrderDetails(transactionId);
        }
    });
    
    // Add hover effect
    $('#sell_table tbody').on('mouseenter', 'tr.clickable-row', function() {
        $(this).css('background-color', '#f5f5f5');
    }).on('mouseleave', 'tr.clickable-row', function() {
        $(this).css('background-color', '');
    });
    
    $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #created_by, #so_list_filter_status, #so_list_shipping_status', function() {
        sell_table.ajax.reload();
    });

    // Debug: Log DataTable AJAX response
    sell_table.on('xhr', function(e, settings, json, xhr) {
        console.log('AJAX Response:', json);
        if (!json || json.data.length === 0) {
            console.warn('No data returned from /sells?sale_type=sales_order');
        }
    });
});
</script>

<!-- Add CSS for better visual feedback -->
<style>
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .clickable-row:hover {
        background-color: #f5f5f5 !important;
    }
    td:nth-child(7) .edit-so-status:hover {
        opacity: 0.8;
        cursor: pointer !important;
    }
</style>
@endsection