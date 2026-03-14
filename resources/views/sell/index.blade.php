@extends('layouts.app')
@section('title', __( 'lang_v1.all_sales'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>@lang( 'sale.sells')
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="box box-info collapsed-box" id="filters_box">
        <div class="box-header with-border" style="cursor: pointer;" id="filters_header">
            <h3 class="box-title">@lang('report.filters')</h3>
        </div>
        <div class="box-body" style="display: none;" id="filters_content">
            <div class="row">
                @include('sell.partials.sell_list_filters')
                @if(!empty($sources))
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('sell_list_filter_source',  __('lang_v1.sources') . ':') !!}
                            {!! Form::select('sell_list_filter_source', $sources, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all') ]); !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'lang_v1.all_sales')])
        @can('direct_sell.access')
            @slot('tool')
                <div class="box-tools">
                    <a class="btn btn-block btn-primary" href="{{action([\App\Http\Controllers\SellController::class, 'create'])}}">
                    <i class="fa fa-plus"></i> @lang('messages.add')</a>
                </div>
            @endslot
        @endcan
        @if(auth()->user()->can('direct_sell.view') ||  auth()->user()->can('view_own_sell_only') ||  auth()->user()->can('view_commission_agent_sell'))
        @php
            $custom_labels = json_decode(session('business.custom_labels'), true);
         @endphp
            <table class="table table-bordered table-striped ajax_view" id="sell_table">
                <thead>
                    <tr>
                        <th>@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>Order No</th>
                        <th>@lang('sale.invoice_no')</th>
                        <th>Multiple Invoice</th>
                        <th>@lang('sale.customer_name')</th>
                        <th>@lang('lang_v1.contact_no')</th>
                        <th>@lang('sale.location')</th>
                        <th>@lang('sale.payment_status')</th>
                        <th>@lang('lang_v1.payment_method')</th>
                        <th>@lang('sale.total_amount')</th>
                        <th>@lang('sale.total_paid')</th>
                        <th>@lang('lang_v1.sell_due')</th>
                        <th>@lang('lang_v1.sell_return_due')</th>
                        <th>@lang('Sell Status')</th>
                        <th>Delivery Person</th>
                        <th>@lang('lang_v1.shipping_status')</th>
                        <th>@lang('lang_v1.total_items')</th>
                        <th>@lang('lang_v1.types_of_service')</th>
                        <th>{{ $custom_labels['types_of_service']['custom_field_1'] ?? __('lang_v1.service_custom_field_1' )}}</th>
                        <th>{{ $custom_labels['sell']['custom_field_1'] ?? '' }}</th>
                        <th>{{ $custom_labels['sell']['custom_field_2'] ?? ''}}</th>
                        <th>{{ $custom_labels['sell']['custom_field_3'] ?? ''}}</th>
                        <th>{{ $custom_labels['sell']['custom_field_4'] ?? ''}}</th>
                        <th>@lang('lang_v1.added_by')</th>
                        <th>@lang('sale.sell_note')</th>
                        <th>@lang('sale.staff_note')</th>
                        <th>@lang('sale.shipping_details')</th>
                        <th>@lang('restaurant.table')</th>
                        <th>@lang('restaurant.service_staff')</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="bg-gray font-17 footer-total text-center">
                        <td colspan="7"><strong>@lang('sale.total'):</strong></td>
                        <td class="footer_payment_status_count"></td>
                        <td class="payment_method_count"></td>
                        <td class="footer_sale_total"></td>
                        <td class="footer_total_paid"></td>
                        <td class="footer_total_remaining"></td>
                        <td class="footer_total_sell_return_due"></td>
                        <td></td>
                        <td></td>
                        <td class="service_type_count"></td>
                        <td colspan="10"></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    @endcomponent
</section>

<div class="modal fade payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
</div>
@stop

@section('javascript')

<script type="text/javascript">
$(document).ready( function(){
    var cacheCheckInterval = null;
    var fastLoadingMode = false;
    
    // ========================================================================
    // A/R FILTER - Read URL params FIRST before anything else
    // ========================================================================
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    // Store globally so DataTable data() function can access them
    window.arFilterActive    = false;
    window.arPaymentStatus   = '';
    window.arStartDate       = '';
    window.arEndDate         = '';
    window.arMonth           = '';
    
    var arMonth          = getUrlParameter('ar_month');
    var urlStartDate     = getUrlParameter('start_date');
    var urlEndDate       = getUrlParameter('end_date');
    var urlPaymentStatus = getUrlParameter('payment_status');
    
    if (arMonth) {
        window.arFilterActive  = true;
        window.arPaymentStatus = urlPaymentStatus;   // e.g. "due,partial"
        window.arStartDate     = urlStartDate;        // e.g. "2025-12-01"
        window.arEndDate       = urlEndDate;          // e.g. "2025-12-31"
        window.arMonth         = arMonth;
        
        // Expand filters
        $('#filters_content').show();
        $('#filters_box').removeClass('collapsed-box');
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

    //Date range as a button
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
    
    // If A/R filter is active, set the date range on the picker NOW (after initialization)
    if (window.arFilterActive && window.arStartDate && window.arEndDate) {
        var arStart = moment(window.arStartDate, 'YYYY-MM-DD');
        var arEnd   = moment(window.arEndDate,   'YYYY-MM-DD');
        var picker  = $('#sell_list_filter_date_range').data('daterangepicker');
        picker.setStartDate(arStart);
        picker.setEndDate(arEnd);
        $('#sell_list_filter_date_range').val(
            arStart.format(moment_date_format) + ' ~ ' + arEnd.format(moment_date_format)
        );
        
        // Show small inline notice banner
        var monthDate = moment(window.arMonth, 'YYYY-MM');
        var arNotice = $('<div id="ar-filter-notice" style="' +
            'display:inline-flex; align-items:center; gap:8px;' +
            'background:#e8f4fd; border:1px solid #b8d4ea; border-radius:4px;' +
            'padding:4px 10px; margin-bottom:8px; font-size:12px; color:#31708f;">' +
            '<i class="fa fa-filter" style="font-size:11px;"></i>' +
            '<span><strong>A/R Filter:</strong> ' + monthDate.format('MMMM YYYY') + ' — Unpaid/Partial</span>' +
            '<a href="#" id="clear-ar-filter" style="' +
            'background:#333; color:#fff; border:none; border-radius:3px;' +
            'padding:2px 7px; font-size:11px; text-decoration:none; cursor:pointer;">' +
            '<i class="fa fa-times" style="font-size:9px;"></i> Clear</a>' +
            '</div>');
        $('#filters_content').prepend(arNotice);
        
        // Clear AR filter button
        $(document).on('click', '#clear-ar-filter', function(e) {
            e.preventDefault();
            window.arFilterActive  = false;
            window.arPaymentStatus = '';
            window.arStartDate     = '';
            window.arEndDate       = '';
            $('#ar-filter-notice').remove();
            
            // Reset date range back to current month (the default)
            var defaultStart = moment().startOf('month');
            var defaultEnd   = moment().endOf('month');
            var picker = $('#sell_list_filter_date_range').data('daterangepicker');
            if (picker) {
                picker.setStartDate(defaultStart);
                picker.setEndDate(defaultEnd);
            }
            $('#sell_list_filter_date_range').val(
                defaultStart.format(moment_date_format) + ' ~ ' + defaultEnd.format(moment_date_format)
            );
            
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            sell_table.ajax.reload();
        });
    }

    sell_table = $('#sell_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        "ajax": {
            "url": "/sells",
            "data": function ( d ) {
                // If A/R filter is active, use stored dates & payment status directly
                if (window.arFilterActive) {
                    d.start_date     = window.arStartDate;
                    d.end_date       = window.arEndDate;
                    d.payment_status = window.arPaymentStatus;
                } else {
                    // Normal filter: read from the date range picker
                    if ($('#sell_list_filter_date_range').val()) {
                        d.start_date = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date   = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.payment_status = $('#sell_list_filter_payment_status').val();
                }

                d.is_direct_sale = 1;
                d.location_id    = $('#sell_list_filter_location_id').val();
                d.customer_id    = $('#sell_list_filter_customer_id').val();
                d.sale_status    = $('#sell_list_filter_sale_status').val();
                d.created_by     = $('#created_by').val();
                d.sales_cmsn_agnt = $('#sales_cmsn_agnt').val();
                d.service_staffs  = $('#service_staffs').val();

                if ($('#shipping_status').length) {
                    d.shipping_status = $('#shipping_status').val();
                }
                if ($('#sell_list_filter_source').length) {
                    d.source = $('#sell_list_filter_source').val();
                }
                if ($('#only_subscriptions').is(':checked')) {
                    d.only_subscriptions = 1;
                }

                d = __datatable_ajax_callback(d);
            },
            "dataSrc": function(json) {
                // Handle fast loading mode
                if (json.fast_initial_load) {
                    fastLoadingMode = true;
                    showFastLoadingMessage(json.message);
                    startCacheMonitoring();
                } else if (json.progressive_load) {
                    showProgressiveLoadingMessage(json.batch_size);
                } else if (json.cached) {
                    fastLoadingMode = false;
                    hideFastLoadingMessage();
                    stopCacheMonitoring();
                }
                
                return json.data;
            }
        },
        scrollY:        "75vh",
        scrollX:        true,
        scrollCollapse: true,
        columns: [
            { data: 'action', name: 'action', orderable: false, "searchable": false},
            { data: 'transaction_date', name: 'transaction_date'  },
            { data: 'sales_order_invoice', name: 'so_trans.invoice_no', "searchable": true},
            { data: 'invoice_no', name: 'invoice_no'},
            { data: 'invoice_no', name: 'invoice_no', "searchable": true, visible: true},
            { data: 'conatct_name', name: 'conatct_name'},
            { data: 'mobile', name: 'contacts.mobile'},
            { data: 'business_location', name: 'bl.name'},
            { data: 'payment_status', name: 'payment_status'},
            { data: 'payment_methods', orderable: false, "searchable": false},
            { data: 'final_total', name: 'final_total'},
            { data: 'total_paid', name: 'total_paid', "searchable": false},
            { data: 'total_remaining', name: 'total_remaining'},
            { data: 'return_due', orderable: false, "searchable": false},
            { data: 'sell_status', name: 'sell_status', orderable: false, "searchable": false},
            { data: 'delivery_person_name', name: 'dp.first_name', "searchable": false},
            { data: 'shipping_status', name: 'shipping_status'},
            { data: 'total_items', name: 'total_items', "searchable": false},
            { data: 'types_of_service_name', name: 'tos.name', @if(empty($is_types_service_enabled)) visible: false @endif},
            { data: 'service_custom_field_1', name: 'service_custom_field_1', @if(empty($is_types_service_enabled)) visible: false @endif},
            { data: 'custom_field_1', name: 'transactions.custom_field_1', @if(empty($custom_labels['sell']['custom_field_1'])) visible: false @endif},
            { data: 'custom_field_2', name: 'transactions.custom_field_2', @if(empty($custom_labels['sell']['custom_field_2'])) visible: false @endif},
            { data: 'custom_field_3', name: 'transactions.custom_field_3', @if(empty($custom_labels['sell']['custom_field_3'])) visible: false @endif},
            { data: 'custom_field_4', name: 'transactions.custom_field_4', @if(empty($custom_labels['sell']['custom_field_4'])) visible: false @endif},
            { data: 'added_by', name: 'u.first_name'},
            { data: 'additional_notes', name: 'additional_notes'},
            { data: 'staff_note', name: 'staff_note'},
            { data: 'shipping_details', name: 'shipping_details'},
            { data: 'table_name', name: 'tables.name', @if(empty($is_tables_enabled)) visible: false @endif },
            { data: 'waiter', name: 'ss.first_name', @if(empty($is_service_staff_enabled)) visible: false @endif },
        ],
        "fnDrawCallback": function (oSettings) {
            __currency_convert_recursively($('#sell_table'));
            initializeRowClickHandler();
            
            // START LAZY CALCULATION
            setTimeout(function() {
                startLazyCalculation();
            }, 50);
        },
        "footerCallback": function ( row, data, start, end, display ) {
            var footer_sale_total = 0;
            var footer_total_paid = 0;
            var footer_total_remaining = 0;
            var footer_total_sell_return_due = 0;
            
            for (var r in data){
                footer_sale_total += $(data[r].final_total).data('orig-value') ? parseFloat($(data[r].final_total).data('orig-value')) : 0;
                footer_total_paid += $(data[r].total_paid).data('orig-value') ? parseFloat($(data[r].total_paid).data('orig-value')) : 0;
                footer_total_remaining += $(data[r].total_remaining).data('orig-value') ? parseFloat($(data[r].total_remaining).data('orig-value')) : 0;
                footer_total_sell_return_due += $(data[r].return_due).find('.sell_return_due').data('orig-value') ? parseFloat($(data[r].return_due).find('.sell_return_due').data('orig-value')) : 0;
            }

            $('.footer_total_sell_return_due').html(__currency_trans_from_en(footer_total_sell_return_due));
            $('.footer_total_remaining').html(__currency_trans_from_en(footer_total_remaining));
            $('.footer_total_paid').html(__currency_trans_from_en(footer_total_paid));
            $('.footer_sale_total').html(__currency_trans_from_en(footer_sale_total));

            $('.footer_payment_status_count').html(__count_status(data, 'payment_status'));
            $('.service_type_count').html(__count_status(data, 'types_of_service_name'));
            $('.payment_method_count').html(__count_status(data, 'payment_methods'));
        },
        createdRow: function( row, data, dataIndex ) {
            $( row ).find('td:eq(7)').attr('class', 'clickable_td');
            $(row).attr('data-transaction-id', data.id);
            $(row).addClass('clickable-row');
        }
    });

    // LAZY CALCULATION - Start calculating rows in background
    function startLazyCalculation() {
        var $rows = $('#sell_table tbody tr');
        if ($rows.length === 0) return;

        var batchSize = 5;
        var currentBatch = 0;

        function processBatch() {
            var startIdx = currentBatch * batchSize;
            var endIdx = Math.min(startIdx + batchSize, $rows.length);

            for (var i = startIdx; i < endIdx; i++) {
                calculateRowCurrency($(($rows)[i]));
            }

            currentBatch++;
            
            if (endIdx < $rows.length) {
                setTimeout(processBatch, 20);
            }
        }

        processBatch();
    }

    // LAZY CALCULATION - Format each row with currency
    function calculateRowCurrency($row) {
        // Find all span elements with data-orig-value
        $row.find('span[data-orig-value]').each(function() {
            var $span = $(this);
            var origValue = parseFloat($span.data('orig-value'));
            
            if (!isNaN(origValue)) {
                var formatted = __currency_trans_from_en(origValue);
                $span.html(formatted);
                $span.addClass('lazy-calculated');
            }
        });
    }

    // Fast loading notification functions
    function showFastLoadingMessage(message) {
        if ($('#fast-loading-notice').length === 0) {
            var notice = `
                <div id="fast-loading-notice" class="alert alert-info" style="margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Fast Mode:</strong> ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            `;
            $('#sell_table_wrapper').prepend(notice);
        }
    }

    function showProgressiveLoadingMessage(batchSize) {
        if ($('#progressive-loading-notice').length === 0) {
            var notice = `
                <div id="progressive-loading-notice" class="alert alert-warning" style="margin-bottom: 10px;">
                    <i class="fas fa-spinner fa-spin"></i> 
                    <strong>Loading:</strong> Loaded ${batchSize} records. Cache building in progress...
                </div>
            `;
            $('#sell_table_wrapper').prepend(notice);
        }
    }

    function hideFastLoadingMessage() {
        $('#fast-loading-notice, #progressive-loading-notice').fadeOut(function() {
            $(this).remove();
        });
    }

    // Cache monitoring
    function startCacheMonitoring() {
        if (cacheCheckInterval) return;
        
        cacheCheckInterval = setInterval(function() {
            checkCacheStatus();
        }, 5000);
    }

    function stopCacheMonitoring() {
        if (cacheCheckInterval) {
            clearInterval(cacheCheckInterval);
            cacheCheckInterval = null;
        }
    }

    function checkCacheStatus() {
        $.ajax({
            url: '/sells/cache-status',
            method: 'GET',
            success: function(response) {
                if (response.exists && !response.building && fastLoadingMode) {
                    console.log('Cache ready, reloading with full data...');
                    fastLoadingMode = false;
                    hideFastLoadingMessage();
                    stopCacheMonitoring();
                    sell_table.ajax.reload();
                    toastr.success('All data loaded successfully!', 'Complete');
                }
            },
            error: function() {
                console.log('Cache status check failed');
            }
        });
    }

    function initializeRowClickHandler() {
        $('#sell_table tbody').off('click', 'tr.clickable-row');
        
        // Main row click handler for transaction details
        $('#sell_table tbody').on('click', 'tr.clickable-row', function(e) {
            if ($(e.target).closest('.btn-group, .dropdown-menu, .dropdown-toggle, a, button, input, select').length) {
                return;
            }
            
            var clickedColumn = $(e.target).closest('td').index();
            if (clickedColumn === 7 || clickedColumn === 14) {
                return;
            }
            
            var transactionId = $(this).data('transaction-id');
            if (transactionId) {
                showTransactionDetails(transactionId);
            }
        });
        
        // Payment status click handler
        $('#sell_table tbody').on('click', 'td:nth-child(9)', function(e) {
            e.stopPropagation();
            var transactionId = $(this).closest('tr').data('transaction-id');
            var paymentStatus = $(this).text().trim();
            if (transactionId) {
                if (paymentStatus !== '') {
                    showPaymentModal(transactionId);
                } else {
                    showTransactionDetails(transactionId);
                }
            }
        });
        
        // Shipping status click handler
        $('#sell_table tbody').on('click', 'td:nth-child(16)', function(e) {
            e.stopPropagation();
            var transactionId = $(this).closest('tr').data('transaction-id');
            var shippingStatus = $(this).text().trim();
            if (transactionId) {
                if (shippingStatus !== '') {
                    showEditShippingModal(transactionId);
                } else {
                    showTransactionDetails(transactionId);
                }
            }
        });
        
        // Add hover effects
        $('#sell_table tbody').on('mouseenter', 'tr.clickable-row', function() {
            $(this).css('background-color', '#f5f5f5');
        }).on('mouseleave', 'tr.clickable-row', function() {
            $(this).css('background-color', '');
        });
        
        // Add special hover for payment column
        $('#sell_table tbody').on('mouseenter', 'td:nth-child(9)', function() {
            var paymentStatus = $(this).text().trim();
            $(this).css('cursor', 'pointer').attr('title', paymentStatus === '' ? 'Click to view details' : 'Click to view payments');
        });
        
        // Add special hover for shipping column
        $('#sell_table tbody').on('mouseenter', 'td:nth-child(16)', function() {
            var shippingStatus = $(this).text().trim();
            $(this).css('cursor', 'pointer').attr('title', shippingStatus === '' ? 'Click to view details' : 'Click to edit shipping');
        });
    }

    // Function to show edit shipping modal
    function showEditShippingModal(transactionId) {
        $.ajax({
            url: '/sells/edit-shipping/' + transactionId,
            method: 'GET',
            success: function(response) {
                if ($('#shipping_modal').length === 0) {
                    $('body').append('<div class="modal fade" id="shipping_modal" tabindex="-1" role="dialog"></div>');
                }
                
                $('#shipping_modal').html(response);
                $('#shipping_modal').modal('show');
                
                if (typeof __currency_convert_recursively === 'function') {
                    __currency_convert_recursively($('#shipping_modal'));
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Failed to load shipping details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                toastr.error(errorMessage);
            }
        });
    }

    // Function to show payment modal using resource route
    function showPaymentModal(transactionId) {
        $.ajax({
            url: '/payments/' + transactionId,
            method: 'GET',
            success: function(response) {
                $('.payment_modal').html(response);
                $('.payment_modal').modal('show');
                
                if (typeof __currency_convert_recursively === 'function') {
                    __currency_convert_recursively($('.payment_modal'));
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Failed to load payment details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                console.log('Payment modal error:', xhr);
                toastr.error(errorMessage);
            }
        });
    }

    // Function to show transaction details in popup
    function showTransactionDetails(transactionId) {
        if ($('#transactionDetailsModal').length === 0) {
            $('body').append('<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog"></div>');
        }
        
        $.ajax({
            url: '/sells/' + transactionId,
            method: 'GET',
            success: function(response) {
                $('#transactionDetailsModal').html(response);
                $('#transactionDetailsModal').modal('show');
                __currency_convert_recursively($('#transactionDetailsModal'));
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Failed to load transaction details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                var errorContent = `
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <h4 class="modal-title">
                                    <i class="fas fa-exclamation-triangle text-danger"></i> Error
                                </h4>
                            </div>
                            <div class="modal-body text-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                                <p class="text-danger" style="margin-top: 15px;">${errorMessage}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#transactionDetailsModal').html(errorContent);
                $('#transactionDetailsModal').modal('show');
            }
        });
    }

    // Filter change handlers
    $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #sell_list_filter_payment_status, #sell_list_filter_sale_status, #created_by, #sales_cmsn_agnt, #service_staffs, #shipping_status, #sell_list_filter_source',  function() {
        fastLoadingMode = false;
        stopCacheMonitoring();
        hideFastLoadingMessage();
        
        // Clear AR filter when user manually changes any filter
        window.arFilterActive  = false;
        window.arPaymentStatus = '';
        window.arStartDate     = '';
        window.arEndDate       = '';
        $('#ar-filter-notice').remove();
        
        sell_table.ajax.reload();
    });

    $('#only_subscriptions').on('ifChanged', function(event){
        fastLoadingMode = false;
        stopCacheMonitoring();
        hideFastLoadingMessage();
        sell_table.ajax.reload();
    });
    
    // Initialize everything
    initializeRowClickHandler();
    
    // Clean up on page unload
    $(window).on('beforeunload', function() {
        stopCacheMonitoring();
    });
});

// Add CSS for lazy calculation
$('<style>')
    .prop('type', 'text/css')
    .html(`
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .clickable-row:hover {
            background-color: #f5f5f5 !important;
        }
        #fast-loading-notice, #progressive-loading-notice {
            border-left: 4px solid #17a2b8;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .lazy-calculated {
            animation: highlight-value 0.4s ease-in-out;
        }
        @keyframes highlight-value {
            0% { background-color: #fffacd; }
            100% { background-color: transparent; }
        }
        .text-muted {
            color: #6c757d !important;
            font-style: italic;
        }
        td:nth-child(9), td:nth-child(16) {
            position: relative;
        }
    `)
    .appendTo('head');
</script>
<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection