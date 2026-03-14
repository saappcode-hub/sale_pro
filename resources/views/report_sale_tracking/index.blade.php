@extends('layouts.app')

@section('title', __('Sale Tracking Report'))

@section('content')
<section class="content-header">
    <h1>{{ __('Sale Tracking Report') }}</h1>
</section>
<style>
    .nav-tabs {
        border-bottom: none;
    }

    .nav-tabs .nav-link:hover {
        color: #0056b3;
    }

    .nav-tabs .nav-link {
        color: black;
    }

    .nav-tabs .nav-link.active {
        color: #0056b3;
        font-weight: bold;
    }

    .boxtie .box .box-body {
        padding: 0px 15px;
    }

    .row {
        margin-right: -0px;
        margin-left: -0px;
    }

    .visit-summary {
        display: none;
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        margin-bottom: 10px;
    }

    .visit-history-container, .map-container-70 {
        height: 100%;
        width: 100%;
        min-height: 600px;
        position: relative;
        overflow: visible;
    }

    .visit-summary-30 {
        flex: 0 0 30%;
        max-width: 30%;
    }

    .map-container-70 {
        flex: 0 0 70%;
        max-width: 70%;
    }

    .tab-content {
        height: 100%;
        min-height: 600px;
    }

    #map.debug {
        border: 2px solid red;
    }

    .gm-style-iw button.gm-ui-hover-effect {
        display: none !important;
    }

    .badge-completed {
        background-color: #28a745;
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
        margin-right: 5px;
    }

    .badge-missed {
        background-color: #dc3545;
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
    }

    #competitor_report_table th, #sales_order_table th {
        white-space: nowrap;
        padding: 8px 20px 8px 5px;
        text-align: center;
        position: relative;
        line-height: 1.5;
    }

    #competitor_report_table th .sorting,
    #sales_order_table th .sorting,
    #competitor_report_table th .sorting_asc,
    #sales_order_table th .sorting_asc,
    #competitor_report_table th .sorting_desc,
    #sales_order_table th .sorting_desc {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-block;
        vertical-align: middle;
    }

    #competitor_report_table, #sales_order_table {
        width: 100% !important;
        table-layout: auto;
    }

    #competitor_report_table th, #competitor_report_table td {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .table-container {
        overflow-x: auto;
    }

    /* Color styles for percentages and status */
    .text-green {
        color: #28a745 !important; /* Green for > 50% or Win */
    }

    .text-red {
        color: #dc3545 !important; /* Red for ≤ 50% or Lost */
    }
</style>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_user_id', __('report.user') . ':') !!}
                {!! Form::select('sell_list_filter_user_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_user_id']) !!}
            </div>
        </div>
        <div class="col-md-3"></div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
        <div class="col-md-3"></div>
    </div>
    @endcomponent
    <div class="row boxtie">
        @component('components.widget', ['class' => 'box-primary'])
            <ul class="nav nav-tabs" role="tablist" style="padding-bottom: 10px;">
                <li class="nav-item">
                    <a class="nav-link active" id="sale-tracking-report-tab" href="javascript:void(0)" role="tab">
                        {{ __('Sale Tracking Report') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="competitor-report-tab" href="javascript:void(0)" role="tab">
                        {{ __('Competitor Report') }}
                    </a>
                </li>
            </ul>
            <div class="row">
                <!-- Sales Order Table -->
                <div class="col-md-12 table-container" id="sales-order-table-container">
                    <table class="table table-bordered table-striped" id="sales_order_table" >
                        <thead>
                            <tr>
                                <th>Sale Rep</th>
                                <th>Number Of Visit(Total)</th>
                                <th>Number Of Visit(Miss)</th>
                                <th>Number Of Visit(Success)</th>
                                <th>Unique Customer</th>
                                <th>Total Product Sold</th>
                                <th>Total Ring Exchange</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be populated dynamically via JavaScript/AJAX -->
                        </tbody>
                    </table>
                </div>
                <!-- Competitor Report Table -->
                <div class="col-md-12 table-container" id="competitor-report-table-container" style="display: none;">
                    <table class="table table-bordered table-striped" id="competitor_report_table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Own Product</th>
                                <th>Own Product(Qty)</th>
                                <th>Comp1</th>
                                <th>Comp1 Qty</th>
                                <th>Comp2</th>
                                <th>Comp2 Qty</th>
                                <th>Total Qty</th>
                                <th>Own Product(%)</th>
                                <th>Competitor(%)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be populated dynamically via JavaScript/AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        @endcomponent
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Initialize Sales Order Table with AJAX to load data by default
    var salesOrderTable = $('#sales_order_table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('sale-tracking-report.data') }}",
            data: function(d) {
                d.user_id = $('#sell_list_filter_user_id').val();
                d.date_range = $('#sell_list_filter_date_range').val();
            }
        },
        columns: [
            { data: 'sale_rep', name: 'sale_rep', width: '14%' },
            { data: 'total_visits', name: 'total_visits', width: '14%' },
            { data: 'missed_visits', name: 'missed_visits', width: '14%' },
            { data: 'success_visits', name: 'success_visits', width: '14%' },
            { data: 'unique_customers', name: 'unique_customers', width: '14%' },
            { data: 'total_products_sold', name: 'total_products_sold', width: '14%' },
            { data: 'total_ring_exchange', name: 'total_ring_exchange', width: '14%' }
        ]
    });

    // Initialize Competitor Report Table with AJAX
    var competitorReportTable = $('#competitor_report_table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('competitor-report.data') }}",
            data: function(d) {
                d.date_range = $('#sell_list_filter_date_range').val();
            }
        },
        columns: [
            { data: 'sku', name: 'sku', width: '9%' },
            { data: 'own_product', name: 'own_product', width: '9%' },
            { data: 'own_product_qty', name: 'own_product_qty', width: '9%' },
            { data: 'comp1', name: 'comp1', width: '9%' },
            { data: 'comp1_qty', name: 'comp1_qty', width: '9%' },
            { data: 'comp2', name: 'comp2', width: '9%' },
            { data: 'comp2_qty', name: 'comp2_qty', width: '9%' },
            { data: 'total_qty', name: 'total_qty', width: '9%' },
            { data: 'own_product_percent', name: 'own_product_percent', width: '9%' },
            { data: 'competitor_percent', name: 'competitor_percent', width: '9%' },
            { data: 'status', name: 'status', width: '9%' }
        ],
        language: {
            emptyTable: "Please apply filters to view data"
        }
    });

    // Function to toggle user filter visibility and reset
    function toggleUserFilter(isCompetitorTab) {
        if (isCompetitorTab) {
            $('#sell_list_filter_user_id').prop('disabled', true);
            $('#sell_list_filter_user_id').val(''); // Reset user filter
        } else {
            $('#sell_list_filter_user_id').prop('disabled', false);
        }
    }

    // Tab click handlers to toggle table visibility
    $('#sale-tracking-report-tab').click(function(e) {
        e.preventDefault();
        $('#sale-tracking-report-tab').addClass('active');
        $('#competitor-report-tab').removeClass('active');
        $('#sales-order-table-container').show();
        $('#competitor-report-table-container').hide();
        toggleUserFilter(false);
    });

    $('#competitor-report-tab').click(function(e) {
        e.preventDefault();
        $('#competitor-report-tab').addClass('active');
        $('#sale-tracking-report-tab').removeClass('active');
        $('#competitor-report-table-container').show();
        $('#sales-order-table-container').hide();
        toggleUserFilter(true);
        // Load data when tab is clicked if not already loaded
        if (!competitorReportTable.data().any()) {
            competitorReportTable.ajax.reload();
        }
    });
    
    // CHANGED: Set default date range to Current Month
    var defaultStartDate = moment().startOf('month'); // First day of this month
    var defaultEndDate = moment().endOf('month');     // Last day of this month
    var defaultDateRange = defaultStartDate.format('MM/DD/YYYY') + ' ~ ' + defaultEndDate.format('MM/DD/YYYY');

    // Initialize Date Range Picker with default range
    $('#sell_list_filter_date_range').daterangepicker({
        startDate: defaultStartDate,
        endDate: defaultEndDate,
        locale: {
            format: 'MM/DD/YYYY', // Enforce MM/DD/YYYY format
            separator: ' ~ ',
            applyLabel: 'Apply',
            cancelLabel: 'Clear',
            fromLabel: 'From',
            toLabel: 'To',
            customRangeLabel: 'Custom',
            daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            firstDay: 1
        },
        autoUpdateInput: false, // Prevent auto-updating input until apply is clicked
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'This Year': [moment().startOf('year'), moment().endOf('year')]
        }
    }, function(start, end) {
        // Format the date range as MM/DD/YYYY
        $('#sell_list_filter_date_range').val(start.format('MM/DD/YYYY') + ' ~ ' + end.format('MM/DD/YYYY'));
        // Apply AJAX and reload the active table
        if ($('#sales-order-table-container').is(':visible')) {
            salesOrderTable.ajax.reload();
        } else if ($('#competitor-report-table-container').is(':visible')) {
            competitorReportTable.ajax.reload();
        }
    });

    // Set initial value for date range input
    $('#sell_list_filter_date_range').val(defaultDateRange);

    // Load tables with default date range
    salesOrderTable.ajax.reload();

    // Handle apply event to ensure correct format
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('MM/DD/YYYY') + ' ~ ' + picker.endDate.format('MM/DD/YYYY'));
    });

    // Handle cancel event to clear the input and reset to default
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val(defaultDateRange); // Reset to default current year range
        // Reload table with default range
        if ($('#sales-order-table-container').is(':visible')) {
            salesOrderTable.ajax.reload();
        } else if ($('#competitor-report-table-container').is(':visible')) {
            competitorReportTable.ajax.reload();
        }
    });

    // Refresh tables when user_id filter changes (only for Sale Tracking Report)
    $('#sell_list_filter_user_id').change(function() {
        if ($('#sales-order-table-container').is(':visible')) {
            salesOrderTable.ajax.reload();
        }
    });
});
</script>
@endsection