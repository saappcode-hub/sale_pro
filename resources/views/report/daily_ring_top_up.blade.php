@extends('layouts.app')
@section('title', __('Ring Report'))

@section('content')

<section class="content-header">
    <h1>{{ __('Ring Report')}}</h1>
</section>

<style>
    .apply-btn-container {
        display: flex;
        align-items: end;
        margin-top: 25px;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
   
    .date-range-error {
        color: #d9534f;
        font-size: 12px;
        margin-top: 5px;
        display: none;
    }
    
    .has-error .form-control,
    .has-error .select2-container--default .select2-selection--single {
        border-color: #d9534f;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 6px #ce8483;
    }
    .has-error .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #d9534f;
    }
    
    #apply_filters_btn:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }
    
    .filters-section {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
    }
    
    .single-row-footer td {
        padding: 2px 8px;
    }
    
    tfoot {
        color: #000 !important;
        background-color: #d2d6de !important;
        font-weight: bold !important;
        border: 1px solid #ddd !important;
        padding: 8px !important;
        text-align: left !important;
    }

    /* Prevent column headers from wrapping */
    #ring_report_table thead th,
    #cash_ring_report_table thead th,
    #ring_top_up_table thead th {
        white-space: nowrap !important;
        vertical-align: middle !important;
    }

    /* Ensure all tables fill full width of their container */
    #ring_report_table,
    #cash_ring_report_table,
    #ring_top_up_table {
        width: 100% !important;
    }

    /* Custom tab styles to match All Ring interface */
    .nav-tabs-custom > .nav-tabs {
        margin: 0;
        border-bottom-color: #ddd;
    }
    .nav-tabs-custom > .nav-tabs > li {
        border-bottom: 0;
        margin-bottom: -1px;
    }
    .nav-tabs-custom > .nav-tabs > li > a {
        color: #444;
        border-radius: 4px 4px 0 0;
        padding: 10px 15px;
        border: 1px solid transparent;
    }
    .nav-tabs-custom > .nav-tabs > li.active {
        border-bottom-color: transparent;
    }
    .nav-tabs-custom > .nav-tabs > li.active > a {
        background-color: #fff;
        border-color: #ddd #ddd transparent;
        color: #555;
    }
    .nav-tabs-custom > .nav-tabs > li:not(.active) > a:hover {
        border-color: #eee #eee #ddd;
        background-color: #eee;
    }
    .nav-tabs-custom > .tab-content {
        background: #fff;
        padding: 20px 24px;
        border-left: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        border-right: 1px solid #ddd;
        border-top: 1px solid #ddd;
        margin-top: -1px;
    }
</style>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, $location_id ?? '', ['class' => 'form-control select2', 'id' => 'sell_list_filter_location_id', 'style' => 'width:100%', 'placeholder' => __('Please Select')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', $date_range ?? '', ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sell_list_filter_date_range', 'readonly']) !!}
                </div>
        </div>
        <div class="col-md-4 apply-btn-container">
            <button type="button" id="apply_filters_btn" class="btn btn-primary">
                <i class="fa fa-search"></i> {{ __('Apply Filters') }}
            </button>
        </div>
    </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#ring_report_tab" data-toggle="tab" aria-expanded="true">
                            <i class="fa fa-table"></i> Ring Stock Movement Report
                        </a>
                    </li>
                    <li>
                        <a href="#cash_ring_report_tab" data-toggle="tab" aria-expanded="false">
                            <i class="fa fa-table"></i> Cash Ring Stock Movement Report
                        </a>
                    </li>
                    <li>
                        <a href="#daily_ring_top_up" data-toggle="tab" aria-expanded="false">
                            <i class="fa fa-arrow-up"></i> Daily Ring Top Up
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="ring_report_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" 
                            id="ring_report_table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="white-space:nowrap">@lang('Ring Name')</th>
                                        <th style="white-space:nowrap">@lang('Beginning Stock (Warehouse)')</th>
                                        <th style="white-space:nowrap">@lang('Ring In')</th>
                                        <th style="white-space:nowrap">@lang('Send to Factory')</th>
                                        <th style="white-space:nowrap">@lang('IN-Warehouse')</th>
                                        <th style="white-space:nowrap">@lang('Beginning Stock (Factory)')</th>
                                        <th style="white-space:nowrap">@lang('Open Ring(Supplier)')</th>
                                        <th style="white-space:nowrap">@lang('Total Ring at Factory')</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="cash_ring_report_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" 
                            id="cash_ring_report_table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="white-space:nowrap">@lang('Ring Name')</th>
                                        <th style="white-space:nowrap">@lang('Beginning Stock (Warehouse)')</th>
                                        <th style="white-space:nowrap">@lang('Ring In')</th>
                                        <th style="white-space:nowrap">@lang('Send to Factory')</th>
                                        <th style="white-space:nowrap">@lang('IN-Warehouse')</th>
                                        <th style="white-space:nowrap">@lang('Open Ring(Supplier)')</th>
                                        <th style="white-space:nowrap">@lang('Total Ring at Factory')</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="daily_ring_top_up">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" 
                            id="ring_top_up_table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>@lang('Ring Name')</th>
                                        <th>@lang('Total Transaction')</th>
                                        <th>@lang('Location')</th>
                                        <th>Unit</th>
                                        <th>@lang('Total Quantity')</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
    var ringTopUpTable;
    var ringReportTable;
    var cashRingReportTable; // NEW: Cash Ring Report Table
    var filters_applied = false;

    // CTN conversion helper: 1 CTN = 23 rings
    var CTN_PER_CARTON = 23;

    function convertCanToCTN(canValue) {
        // Expects a string like "68828 Can" or a number
        var qty = 0;
        var raw = String(canValue || '');
        if (raw.indexOf('Can') !== -1) {
            qty = parseInt(raw.replace('Can', '').replace(/,/g, '').trim(), 10) || 0;
        } else if (!isNaN(parseFloat(raw))) {
            qty = parseInt(raw, 10) || 0;
        } else {
            return canValue; // Not a Can value — return as-is
        }
        var ctn   = Math.floor(qty / CTN_PER_CARTON);
        var rings = qty % CTN_PER_CARTON;
        if (ctn > 0 && rings > 0) {
            return ctn + ' CTN ' + rings + ' rings';
        } else if (ctn > 0) {
            return ctn + ' CTN';
        } else {
            return rings + ' rings';
        }
    }

    function convertTotalCanToCTN(totalCans) {
        var ctn   = Math.floor(totalCans / CTN_PER_CARTON);
        var rings = totalCans % CTN_PER_CARTON;
        if (ctn > 0 && rings > 0) {
            return ctn + ' CTN ' + rings + ' rings';
        } else if (ctn > 0) {
            return ctn + ' CTN';
        } else {
            return rings + ' rings';
        }
    }

    // Function to clear all error states
    function clearErrorStates() {
        $('#sell_list_filter_date_range').removeClass('has-error');
        $('#sell_list_filter_location_id').removeClass('has-error');
    }

    // REMOVED: validateDateRange function
    // REMOVED: showDateRangeError function

    // Initialize date range picker
    var dateRangeSettings = {
        startDate: moment().startOf('month'),
        endDate: moment(),
        maxDate: moment().add(5, 'year'), // MODIFIED: Increased maxDate
        minDate: moment().subtract(10, 'years'), // MODIFIED: Increased minDate
        locale: {
            format: moment_date_format || 'YYYY-MM-DD',
            separator: ' ~ ',
            applyLabel: 'Apply',
            cancelLabel: 'Cancel',
            fromLabel: 'From',
            toLabel: 'To',
            customRangeLabel: 'Custom',
            daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            firstDay: 1
        },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            // MODIFIED: Added This Year and Last Year
            'This Year': [moment().startOf('year'), moment().endOf('year')],
            'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
        }
    };

    // Initialize date range picker
    // MODIFIED: Removed the main callback function
    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings
    );

    // Initialize Ring Stock Movement Report DataTable (DEFAULT) - NO DATA LOADS UNTIL APPLY FILTERS
    ringReportTable = $('#ring_report_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reports.daily-ring-top-up') }}",
            type: 'GET',
            data: function(d) {
                var location_id = $('#sell_list_filter_location_id').val();
                var date_range = $('#sell_list_filter_date_range').val();
                
                d.sell_list_filter_location_id = location_id;
                d.sell_list_filter_date_range = date_range;
                d.active_tab = 'ring_report';
                d.ajax = 1;
                
                // CRITICAL: Only send data if Apply Filters was clicked
                if (filters_applied && location_id && date_range) {
                    d.apply_filters = '1';
                    var start = $('input#sell_list_filter_date_range')
                        .data('daterangepicker')
                        .startDate.format('YYYY-MM-DD');
                    var end = $('input#sell_list_filter_date_range')
                        .data('daterangepicker')
                        .endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                } else {
                    d.apply_filters = '0';
                }
            },
            error: function(xhr, error, thrown) {
                console.log('AJAX Error: ', xhr.responseText);
                if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                    toastr.error(xhr.responseJSON.error);
                } else if (xhr.status !== 200) {
                    toastr.error('An error occurred while loading the report.');
                }
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
            }
        },
        columns: [
            { data: 'Ring Name',                    name: 'Ring Name',                    title: 'Ring Name',                    className: 'nowrap' },
            { data: 'Beginning Stock (Warehouse)',   name: 'Beginning Stock (Warehouse)',   title: 'Beginning Stock (Warehouse)',  className: 'nowrap' },
            { data: 'Ring In',                       name: 'Ring In',                       title: 'Ring In',                      className: 'nowrap' },
            { data: 'Send to Factory',               name: 'Send to Factory',               title: 'Send to Factory',              className: 'nowrap' },
            { data: 'IN-Warehouse',                  name: 'IN-Warehouse',                  title: 'IN-Warehouse',                 className: 'nowrap' },
            { data: 'Beginning Stock (Factory)',     name: 'Beginning Stock (Factory)',     title: 'Beginning Stock (Factory)',    className: 'nowrap' },
            { data: 'Open Ring(Supplier)',            name: 'Open Ring(Supplier)',            title: 'Open Ring(Supplier)',          className: 'nowrap' },
            { data: 'Total Ring at Factory',         name: 'Total Ring at Factory',         title: 'Total Ring at Factory',        className: 'nowrap' }
        ],
        language: {
            emptyTable: "Please select location and date range, then click Apply Filters to load data",
            zeroRecords: "No matching records found for the selected filters.",
            processing: "Loading ring report data..."
        },
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        autoWidth: false,
        drawCallback: function() {
            overrideExistingExportButtons();
        },
        headerCallback: function(thead, data, start, end, display) {
            $(thead).find('th').css('white-space', 'nowrap');
        },
        deferLoading: true // Don't load data initially
    });

    // Tab switching logic - modified to include new cash ring report tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr('href');
        
        if (target == '#daily_ring_top_up') {
            if (typeof (ringTopUpTable) == 'undefined') {
                ringTopUpTable = $('#ring_top_up_table').DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    ajax: {
                        url: "{{ route('reports.daily-ring-top-up') }}",
                        type: 'GET',
                        data: function(d) {
                            var location_id = $('#sell_list_filter_location_id').val();
                            var date_range = $('#sell_list_filter_date_range').val();
                            
                            d.sell_list_filter_location_id = location_id;
                            d.sell_list_filter_date_range = date_range;
                            d.active_tab = 'top_up';
                            d.ajax = 1;
                            
                            if (filters_applied && location_id && date_range) {
                                d.apply_filters = '1';
                                var start = $('input#sell_list_filter_date_range')
                                    .data('daterangepicker')
                                    .startDate.format('YYYY-MM-DD');
                                var end = $('input#sell_list_filter_date_range')
                                    .data('daterangepicker')
                                    .endDate.format('YYYY-MM-DD');
                                d.start_date = start;
                                d.end_date = end;
                            } else {
                                d.apply_filters = '0';
                            }
                        },
                        error: function(xhr, error, thrown) {
                            console.log('AJAX Error: ', xhr.responseText);
                            if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                                toastr.error(xhr.responseJSON.error);
                            } else if (xhr.status !== 200) {
                                toastr.error('An error occurred while loading the report.');
                            }
                            $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                        }
                    },
                    columns: [
                        { data: 'ring_name', name: 'ring_name', title: 'Ring Name' },
                        { data: 'total_transaction', name: 'total_transaction', title: 'Total Transaction' },
                        { data: 'location', name: 'location', title: 'Location' },
                        { data: 'unit', name: 'unit', title: 'Unit', render: function(data) { return data ? data : '-'; } },
                        { data: 'total_quantity', name: 'total_quantity', title: 'Total Quantity', render: function(data) {
                            if (data && String(data).indexOf('Can') !== -1) {
                                return convertCanToCTN(data);
                            }
                            return data;
                        } }
                    ],
                    language: {
                        emptyTable: "Please select location and date range, then click Apply Filters to load data",
                        zeroRecords: "No matching records found for the selected filters.",
                        processing: "Loading ring top-up data..."
                    },
                    order: [[0, 'desc']],
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    footerCallback: function (row, data, start, end, display) {
                        var api = this.api();
                        
                        var serverData = api.ajax.json();
                        var totalTransactionFromServer = serverData && serverData.totalTransactionSum ? serverData.totalTransactionSum : 0;

                        var totalNormal = 0;
                        var totalCash = 0;

                        // Use ALL pages data for totals (server-provided totals are more reliable,
                        // but we also accumulate from current page for the Can→CTN conversion)
                        // NOTE: api.column().data() returns RAW server values (e.g. "68828 Can"),
                        // NOT the rendered output, so we parse the original Can string directly.
                        api.column(4, { page: 'current' }).data().each(function (value, index) {
                            var ringName = api.column(0).data()[index].toLowerCase();
                            var raw = String(value || '');
                            if (raw.indexOf('Can') !== -1) {
                                var quantity = parseInt(raw.replace('Can', '').replace(/,/g, '').trim(), 10) || 0;
                                if (ringName.indexOf('(can)') !== -1) {
                                    totalNormal += quantity;
                                }
                            } else if (raw.indexOf('$') !== -1) {
                                var quantity = parseFloat(raw.replace('$', '').replace(/,/g, '')) || 0;
                                if (ringName.indexOf('cash') !== -1) {
                                    totalCash += quantity;
                                }
                            }
                        });

                        var canDisplay = convertTotalCanToCTN(totalNormal);
                        $(api.column(1).footer()).html(totalTransactionFromServer + ' unique transaction');
                        $(api.column(4).footer()).html(canDisplay + ' | ' + (totalCash > 0 ? '$' + totalCash.toFixed(3) : '$0.00'));
                    },
                    drawCallback: function() {
                        $('tfoot tr').addClass('single-row-footer');
                        overrideExistingExportButtons();
                    },
                    deferLoading: true
                });
            }
        } else if (target == '#cash_ring_report_tab') {
            // NEW: Initialize Cash Ring Stock Movement Report
            if (typeof (cashRingReportTable) == 'undefined') {
                cashRingReportTable = $('#cash_ring_report_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('reports.daily-ring-top-up') }}",
                        type: 'GET',
                        data: function(d) {
                            var location_id = $('#sell_list_filter_location_id').val();
                            var date_range = $('#sell_list_filter_date_range').val();
                            
                            d.sell_list_filter_location_id = location_id;
                            d.sell_list_filter_date_range = date_range;
                            d.active_tab = 'cash_ring_report'; // NEW tab identifier
                            d.ajax = 1;
                            
                            if (filters_applied && location_id && date_range) {
                                d.apply_filters = '1';
                                var start = $('input#sell_list_filter_date_range')
                                    .data('daterangepicker')
                                    .startDate.format('YYYY-MM-DD');
                                var end = $('input#sell_list_filter_date_range')
                                    .data('daterangepicker')
                                    .endDate.format('YYYY-MM-DD');
                                d.start_date = start;
                                d.end_date = end;
                            } else {
                                d.apply_filters = '0';
                            }
                        },
                        error: function(xhr, error, thrown) {
                            console.log('AJAX Error: ', xhr.responseText);
                            if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                                toastr.error(xhr.responseJSON.error);
                            } else if (xhr.status !== 200) {
                                toastr.error('An error occurred while loading the cash ring report.');
                            }
                            $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                        }
                    },
                    columns: [
                        { data: 'Ring Name',                   name: 'Ring Name',                   title: 'Ring Name',                   className: 'nowrap' },
                        { data: 'Beginning Stock (Warehouse)', name: 'Beginning Stock (Warehouse)', title: 'Beginning Stock (Warehouse)', className: 'nowrap' },
                        { data: 'Ring In',                     name: 'Ring In',                     title: 'Ring In',                     className: 'nowrap' },
                        { data: 'Send to Factory',             name: 'Send to Factory',             title: 'Send to Factory',             className: 'nowrap' },
                        { data: 'IN-Warehouse',                name: 'IN-Warehouse',                title: 'IN-Warehouse',                className: 'nowrap' },
                        { data: 'Open Ring(Supplier)',         name: 'Open Ring(Supplier)',         title: 'Open Ring(Supplier)',         className: 'nowrap' },
                        { data: 'Total Ring at Factory',       name: 'Total Ring at Factory',       title: 'Total Ring at Factory',       className: 'nowrap' }
                    ],
                    language: {
                        emptyTable: "Please select location and date range, then click Apply Filters to load data",
                        zeroRecords: "No matching records found for the selected filters.",
                        processing: "Loading cash ring report data..."
                    },
                    order: [[0, 'asc']],
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    autoWidth: false,
                    drawCallback: function() {
                        overrideExistingExportButtons();
                    },
                    headerCallback: function(thead, data, start, end, display) {
                        $(thead).find('th').css('white-space', 'nowrap');
                    },
                    deferLoading: true
                });
            }
        }
    });

    // Event handlers for filter changes - RESET filters_applied flag
    $('#sell_list_filter_location_id').on('change', function() {
        clearErrorStates();
        filters_applied = false; // Reset when user changes filters
    });

    $('#sell_list_filter_date_range').on('show.daterangepicker', function(ev, picker) {
        clearErrorStates();
    });

    // MODIFIED: This event now handles setting the value AND the flag.
    // This event fires when a predefined range is clicked OR when the "Apply" button is clicked.
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        var format = moment_date_format || 'YYYY-MM-DD';
        var startDate = picker.startDate;
        var endDate = picker.endDate;
        
        // Explicitly set the value of the input
        $(this).val(startDate.format(format) + ' ~ ' + endDate.format(format));
        
        // Reset the filters_applied flag, forcing user to click the main "Apply Filters" button
        filters_applied = false; 
    });

    // Function to get all data for export
    function getAllDataForExport(callback) {
        // Get current table ordering from active table
        var activeTable, params;
        
        if ($("#ring_report_tab").hasClass('active') && typeof (ringReportTable) !== 'undefined') {
            activeTable = ringReportTable;
            params = {
                sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
                sell_list_filter_date_range: $('#sell_list_filter_date_range').val(),
                apply_filters: filters_applied ? '1' : '0',
                active_tab: 'ring_report',
                length: -1, // Get all records
                start: 0,
                ajax: 1
            };
        } else if ($("#cash_ring_report_tab").hasClass('active') && typeof (cashRingReportTable) !== 'undefined') {
            // NEW: Export for Cash Ring Report
            activeTable = cashRingReportTable;
            params = {
                sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
                sell_list_filter_date_range: $('#sell_list_filter_date_range').val(),
                apply_filters: filters_applied ? '1' : '0',
                active_tab: 'cash_ring_report',
                length: -1,
                start: 0,
                ajax: 1
            };
        } else if ($("#daily_ring_top_up").hasClass('active') && typeof (ringTopUpTable) !== 'undefined') {
            activeTable = ringTopUpTable;
            var currentOrder = ringTopUpTable.order();
            var orderColumn = currentOrder[0][0];
            var orderDirection = currentOrder[0][1];
            
            params = {
                sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
                sell_list_filter_date_range: $('#sell_list_filter_date_range').val(),
                apply_filters: filters_applied ? '1' : '0',
                active_tab: 'top_up',
                length: -1,
                start: 0,
                ajax: 1,
                'order[0][column]': orderColumn,
                'order[0][dir]': orderDirection,
                'columns[0][data]': 'ring_name',
                'columns[0][name]': 'ring_name',
                'columns[1][data]': 'total_transaction',
                'columns[1][name]': 'total_transaction',
                'columns[2][data]': 'location', 
                'columns[2][name]': 'location',
                'columns[3][data]': 'unit',
                'columns[3][name]': 'unit',
                'columns[4][data]': 'total_quantity',
                'columns[4][name]': 'total_quantity'
            };
        }
        
        if (filters_applied) {
            var start = $('input#sell_list_filter_date_range')
                .data('daterangepicker')
                .startDate.format('YYYY-MM-DD');
            var end = $('input#sell_list_filter_date_range')
                .data('daterangepicker')
                .endDate.format('YYYY-MM-DD');
            params.start_date = start;
            params.end_date = end;
        }
        
        $.ajax({
            url: "{{ route('reports.daily-ring-top-up') }}",
            type: 'GET',
            data: params,
            success: function(response) {
                callback(response.data || []);
            },
            error: function() {
                callback([]);
                toastr.error('Failed to fetch export data');
            }
        });
    }

    // Function to process data for export (handles unit column properly)
    function processDataForExport(data) {
        if (!data || data.length === 0) {
            return [];
        }

        var processedData = [];
        
        data.forEach(function(row) {
            // Handle different tab structures
            if ($("#daily_ring_top_up").hasClass('active')) {
                // Daily Ring Top Up tab
                var unitClean = row.unit;
                if (unitClean && unitClean !== '-') {
                    unitClean = unitClean.replace(/<br\s*\/?>/gi, '\n');
                    unitClean = unitClean.replace(/<\/br>/gi, '\n');
                    unitClean = unitClean.replace(/&nbsp;/g, ' ');
                    unitClean = unitClean.replace(/<[^>]*>/g, '');
                    unitClean = unitClean.trim();
                    
                    if (unitClean === '') {
                        unitClean = '-';
                    }
                } else {
                    unitClean = '-';
                }
                
                processedData.push([
                    row.ring_name || '',
                    row.total_transaction || '',
                    row.location || '',
                    unitClean,
                    (function() {
                        var qty = (row.total_quantity || '').toString().replace(/<[^>]*>/g, '').trim();
                        if (qty.indexOf('Can') !== -1) {
                            return convertCanToCTN(qty);
                        }
                        return qty;
                    })()
                ]);
            } else {
                // Ring Report tab OR Cash Ring Report tab (same structure)
                processedData.push([
                    row['Ring Name'] || '',
                    row['Beginning Stock (Warehouse)'] || '',
                    row['Ring In'] || '',
                    row['Send to Factory'] || '',
                    row['IN-Warehouse'] || '',
                    row['Open Ring(Supplier)'] || '',
                    row['Total Ring at Factory'] || ''
                ]);
            }
        });
        
        return processedData;
    }

    // Excel export with proper unit handling for both Daily Ring Top Up and Cash Ring Stock Movement Report
    function exportToExcelWithSeparateRows(data) {
        var headers, expandedData = [], mergeRanges = [];
        
        if ($("#daily_ring_top_up").hasClass('active')) {
            // Existing Daily Ring Top Up logic (unchanged)
            headers = ['Ring Name', 'Total Transaction', 'Location', 'Unit', 'Total Quantity'];
            var currentRowIndex = 1;
            
            data.forEach(function(row) {
                var unitValue = row.unit;
                
                if (unitValue && unitValue !== '-' && unitValue.indexOf('<br') !== -1) {
                    var cleanUnit = unitValue.replace(/<br\s*\/?>/gi, '\n')
                                        .replace(/<[^>]*>/g, '')
                                        .replace(/&nbsp;/g, ' ')
                                        .trim();
                    
                    var unitItems = cleanUnit.split('\n').filter(function(item) {
                        return item.trim() !== '';
                    });
                    
                    var startRowIndex = currentRowIndex;
                    
                    unitItems.forEach(function(unitItem, index) {
                        expandedData.push([
                            index === 0 ? (row.ring_name || '') : '',
                            index === 0 ? (row.total_transaction || '') : '',
                            index === 0 ? (row.location || '') : '',
                            unitItem.trim(),
                            index === 0 ? (function() {
                                var qty = ((row.total_quantity || '').toString().replace(/<[^>]*>/g, '').trim());
                                if (qty.indexOf('Can') !== -1) { return convertCanToCTN(qty); }
                                return qty;
                            })() : ''
                        ]);
                        currentRowIndex++;
                    });
                    
                    if (unitItems.length > 1) {
                        var endRowIndex = currentRowIndex - 1;
                        
                        mergeRanges.push(
                            { s: { r: startRowIndex, c: 0 }, e: { r: endRowIndex, c: 0 } },
                            { s: { r: startRowIndex, c: 1 }, e: { r: endRowIndex, c: 1 } },
                            { s: { r: startRowIndex, c: 2 }, e: { r: endRowIndex, c: 2 } },
                            { s: { r: startRowIndex, c: 4 }, e: { r: endRowIndex, c: 4 } }
                        );
                    }
                } else {
                    expandedData.push([
                        row.ring_name || '',
                        row.total_transaction || '',
                        row.location || '',
                        unitValue && unitValue !== '-' ? unitValue.replace(/<[^>]*>/g, '').trim() : '',
                        (function() {
                            var qty = (row.total_quantity || '').toString().replace(/<[^>]*>/g, '').trim();
                            if (qty.indexOf('Can') !== -1) { return convertCanToCTN(qty); }
                            return qty;
                        })()
                    ]);
                    currentRowIndex++;
                }
            });
            
            // Set column widths for Daily Ring Top Up
            var colWidths = [
                { wch: 25 }, { wch: 15 }, { wch: 20 }, { wch: 35 }, { wch: 18 }
            ];
            
        } else if ($("#cash_ring_report_tab").hasClass('active')) {
            // Cash Ring Stock Movement Report with separate unit columns and no dashes
            headers = ['Ring Name', 'Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'];
            var currentRowIndex = 1;
            
            data.forEach(function(row) {
                // Find columns that contain unit conversions (have <br> tags)
                var unitColumns = ['Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'];
                var hasUnitConversions = false;
                var maxUnits = 1;
                var columnUnits = {};
                
                // Parse unit conversions for each column
                unitColumns.forEach(function(colName) {
                    var colValue = row[colName] || '';
                    if (colValue && colValue !== '-' && colValue.indexOf('<br') !== -1) {
                        var cleanUnit = colValue.replace(/<br\s*\/?>/gi, '\n')
                                            .replace(/<\/br>/gi, '\n')
                                            .replace(/<[^>]*>/g, '')
                                            .replace(/&nbsp;/g, ' ')
                                            .trim();
                        
                        var unitItems = cleanUnit.split('\n').filter(function(item) {
                            return item.trim() !== '';
                        });
                        
                        columnUnits[colName] = unitItems;
                        maxUnits = Math.max(maxUnits, unitItems.length);
                        hasUnitConversions = true;
                    } else {
                        columnUnits[colName] = [colValue && colValue !== '-' ? colValue : ''];
                    }
                });
                
                if (hasUnitConversions) {
                    var startRowIndex = currentRowIndex;
                    
                    // Create rows for each unit conversion
                    for (var i = 0; i < maxUnits; i++) {
                        var rowData = [
                            i === 0 ? (row['Ring Name'] || '') : '', // Ring Name (merge)
                            columnUnits['Beginning Stock (Warehouse)'][i] || '', // Beginning Stock units
                            columnUnits['Ring In'][i] || '', // Ring In units
                            columnUnits['Send to Factory'][i] || '', // Send to Factory units
                            columnUnits['IN-Warehouse'][i] || '', // IN-Warehouse units
                            columnUnits['Open Ring(Supplier)'][i] || '', // Open Ring units
                            columnUnits['Total Ring at Factory'][i] || '' // Total Ring at Factory units
                        ];
                        expandedData.push(rowData);
                        currentRowIndex++;
                    }
                    
                    if (maxUnits > 1) {
                        var endRowIndex = currentRowIndex - 1;
                        
                        // Merge cells only for Ring Name column
                        mergeRanges.push(
                            { s: { r: startRowIndex, c: 0 }, e: { r: endRowIndex, c: 0 } } // Ring Name
                        );
                    }
                } else {
                    // No unit conversions, add single row without dashes
                    expandedData.push([
                        row['Ring Name'] || '',
                        row['Beginning Stock (Warehouse)'] && row['Beginning Stock (Warehouse)'] !== '-' ? row['Beginning Stock (Warehouse)'] : '',
                        row['Ring In'] && row['Ring In'] !== '-' ? row['Ring In'] : '',
                        row['Send to Factory'] && row['Send to Factory'] !== '-' ? row['Send to Factory'] : '',
                        row['IN-Warehouse'] && row['IN-Warehouse'] !== '-' ? row['IN-Warehouse'] : '',
                        row['Open Ring(Supplier)'] && row['Open Ring(Supplier)'] !== '-' ? row['Open Ring(Supplier)'] : '',
                        row['Total Ring at Factory'] && row['Total Ring at Factory'] !== '-' ? row['Total Ring at Factory'] : ''
                    ]);
                    currentRowIndex++;
                }
            });
            
            // Set column widths for Cash Ring Report
            var colWidths = [
                { wch: 25 }, { wch: 20 }, { wch: 15 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 20 }
            ];
            
        } else {
            // Regular Ring Report tab (no unit conversions)
            headers = ['Ring Name', 'Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'];
            
            data.forEach(function(row) {
                expandedData.push([
                    row['Ring Name'] || '',
                    row['Beginning Stock (Warehouse)'] || '',
                    row['Ring In'] || '',
                    row['Send to Factory'] || '',
                    row['IN-Warehouse'] || '',
                    row['Open Ring(Supplier)'] || '',
                    row['Total Ring at Factory'] || ''
                ]);
            });
            
            var colWidths = [
                { wch: 25 }, { wch: 20 }, { wch: 15 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 20 }
            ];
        }
        
        // Create workbook and worksheet
        var wb = XLSX.utils.book_new();
        var wsData = [headers].concat(expandedData);
        var ws = XLSX.utils.aoa_to_sheet(wsData);
        
        // Apply merges if any
        if (mergeRanges.length > 0) {
            ws['!merges'] = mergeRanges;
        }
        
        // Set column widths
        ws['!cols'] = colWidths;
        
        // Apply styling
        var range = XLSX.utils.decode_range(ws['!ref']);
        for (var R = range.s.r; R <= range.e.r; ++R) {
            for (var C = range.s.c; C <= range.e.c; ++C) {
                var cellAddress = XLSX.utils.encode_cell({r: R, c: C});
                if (!ws[cellAddress]) continue;
                
                var cell = ws[cellAddress];
                if (!cell.s) cell.s = {};
                
                if (R === 0) {
                    // Header styling
                    cell.s = {
                        font: { bold: true, color: { rgb: '000000' } },
                        fill: { fgColor: { rgb: 'D2D6DE' } },
                        alignment: { horizontal: 'center', vertical: 'center' },
                        border: {
                            top: { style: 'thin' }, bottom: { style: 'thin' },
                            left: { style: 'thin' }, right: { style: 'thin' }
                        }
                    };
                } else {
                    // Data cell styling
                    cell.s.border = {
                        top: { style: 'thin' }, bottom: { style: 'thin' },
                        left: { style: 'thin' }, right: { style: 'thin' }
                    };
                    cell.s.alignment = { horizontal: 'center', vertical: 'center' };
                }
            }
        }
        
        // Set sheet name and file name based on active tab
        var sheetName, fileName;
        if ($("#daily_ring_top_up").hasClass('active')) {
            sheetName = 'Daily Ring Top Up';
            fileName = 'daily_ring_top_up_report.xlsx';
        } else if ($("#cash_ring_report_tab").hasClass('active')) {
            sheetName = 'Cash Ring Stock Movement';
            fileName = 'cash_ring_stock_movement_report.xlsx';
        } else {
            sheetName = 'Ring Stock Movement';
            fileName = 'ring_stock_movement_report.xlsx';
        }
        
        XLSX.utils.book_append_sheet(wb, ws, sheetName);
        
        try {
            XLSX.writeFile(wb, fileName, { bookType: 'xlsx', cellStyles: true });
            toastr.success('Excel export completed successfully!');
        } catch (error) {
            console.error('Excel export error:', error);
            toastr.error('Export failed: ' + error.message);
        }
    }

    // CSV Export function
    function exportToCSVWithLineBreaks(data) {
        var processedData = processDataForExport(data);
        var headers, fileName;
        
        if ($("#daily_ring_top_up").hasClass('active')) {
            headers = ['Ring Name', 'Total Transaction', 'Location', 'Unit', 'Total Quantity'];
            fileName = 'daily_ring_top_up_report.csv';
        } else if ($("#cash_ring_report_tab").hasClass('active')) {
            headers = ['Ring Name', 'Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'];
            fileName = 'cash_ring_stock_movement_report.csv';
        } else {
            headers = ['Ring Name', 'Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'];
            fileName = 'ring_stock_movement_report.csv';
        }
        
        var csvContent = [headers.join(',')];
        
        processedData.forEach(function(row) {
            var csvRow = row.map(function(value) {
                var stringValue = String(value || '');
                if (stringValue.indexOf(',') !== -1 || stringValue.indexOf('"') !== -1 || stringValue.indexOf('\n') !== -1) {
                    stringValue = '"' + stringValue.replace(/"/g, '""') + '"';
                }
                return stringValue;
            });
            csvContent.push(csvRow.join(','));
        });
        
        var blob = new Blob([csvContent.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', fileName);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        toastr.success('CSV export completed');
    }

    // Function to override existing export buttons
    function overrideExistingExportButtons() {
        setTimeout(function() {
            $('button[data-original-title*="CSV"], .buttons-csv, button:contains("CSV")').off('click').on('click', function(e) {
                e.preventDefault();
                
                if (!filters_applied) {
                    toastr.warning('Please apply filters first to export data');
                    return;
                }
                
                getAllDataForExport(function(data) {
                    if (data.length === 0) {
                        toastr.warning('No data to export');
                        return;
                    }
                    exportToCSVWithLineBreaks(data);
                });
            });
            
            $('button[data-original-title*="Excel"], .buttons-excel, button:contains("Excel")').off('click').on('click', function(e) {
                e.preventDefault();
                
                if (!filters_applied) {
                    toastr.warning('Please apply filters first to export data');
                    return;
                }
                
                getAllDataForExport(function(data) {
                    if (data.length === 0) {
                        toastr.warning('No data to export');
                        return;
                    }
                    exportToExcelWithSeparateRows(data);
                });
            });
        }, 1000);
    }

    // APPLY FILTERS BUTTON - THE ONLY WAY TO LOAD DATA
    // MODIFIED: Removed all 1-month (31-day) validation logic
    $('#apply_filters_btn').click(function() {
        var location_id = $('#sell_list_filter_location_id').val();
        var date_range = $('#sell_list_filter_date_range').val();
        var hasErrors = false;

        // Clear previous errors
        $('#sell_list_filter_location_id').removeClass('has-error');
        $('#sell_list_filter_date_range').removeClass('has-error');

        // Validate location
        if (!location_id || location_id === '') {
            $('#sell_list_filter_location_id').addClass('has-error');
            toastr.error('{{ __("Please select a business location") }}');
            hasErrors = true;
        }
        
        // Validate date range
        if (!date_range || date_range === '') {
            $('#sell_list_filter_date_range').addClass('has-error');
            toastr.error('{{ __("Please select a date range") }}');
            hasErrors = true;
        }

        if (hasErrors) {
            return;
        }

        // Validate date range format
        if (date_range) {
            var dates = date_range.split(' ~ ');
            if (dates.length === 2) {
                var format = moment_date_format || 'YYYY-MM-DD';
                var startDate = moment(dates[0], format);
                var endDate = moment(dates[1], format);
                
                // REMOVED: 31-day validation block

                if (!startDate.isValid() || !endDate.isValid()) {
                    toastr.error('Invalid date format. Please select a valid date range.');
                    $('#sell_list_filter_date_range').addClass('has-error');
                    return;
                }
            } else {
                toastr.error('Please select a valid date range.');
                $('#sell_list_filter_date_range').addClass('has-error');
                return;
            }
        }

        // All validations passed - proceed with loading data
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Loading...") }}');

        // SET THE FLAG TO ALLOW DATA LOADING
        filters_applied = true;

        // Reload appropriate table based on active tab
        if ($("#ring_report_tab").hasClass('active') && typeof (ringReportTable) !== 'undefined') {
            ringReportTable.ajax.reload(function() {
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                
                var info = ringReportTable.page.info();
                if (info.recordsTotal > 0) {
                    toastr.success('Ring stock movement report loaded successfully. Found ' + info.recordsTotal + ' records.');
                } else {
                    toastr.info('No records found for the selected criteria.');
                }
            }, false);
        } else if ($("#cash_ring_report_tab").hasClass('active') && typeof (cashRingReportTable) !== 'undefined') {
            // NEW: Cash Ring Report tab is active
            cashRingReportTable.ajax.reload(function() {
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                
                var info = cashRingReportTable.page.info();
                if (info.recordsTotal > 0) {
                    toastr.success('Cash Ring stock movement report loaded successfully. Found ' + info.recordsTotal + ' records.');
                } else {
                    toastr.info('No records found for the selected criteria.');
                }
            }, false);
        } else if ($("#daily_ring_top_up").hasClass('active') && typeof (ringTopUpTable) !== 'undefined') {
            // Daily Ring Top Up tab is active
            ringTopUpTable.ajax.reload(function() {
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                
                var info = ringTopUpTable.page.info();
                if (info.recordsTotal > 0) {
                    toastr.success('Daily Ring Top Up loaded successfully. Found ' + info.recordsTotal + ' records.');
                } else {
                    toastr.info('No records found for the selected criteria.');
                }
            }, false);
        }
    });
});
</script>
@endsection