@extends('layouts.app')

@section('title', __('Daily Sales & Payment Report'))

@section('content')
<section class="content-header">
    <h1>{{ __('Daily Sales & Payment Report') }}</h1>
</section>
<style>
    .status-completed {
        background-color: #20c997;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-partial {
        background-color: #328AC9;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .filter-section {
        background-color: #f4f4f4;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .apply-btn-container {
        display: flex;
        align-items: end;
        margin-top: 25px;
    }
    /* Add styles for horizontal scrolling */
    .table-responsive {
        overflow-x: auto; /* Enable horizontal scrolling */
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on mobile */
    }
    #daily_sales_payment_report_table {
        min-width: 1000px; /* Ensure minimum width to trigger scrolling if needed */
        width: auto; /* Allow table to expand based on content */
    }
    #daily_sales_payment_report_table th,
    #daily_sales_payment_report_table td {
        white-space: nowrap; /* Prevent text wrapping */
    }
    /* Date range validation message */
    .date-range-error {
        color: #d9534f;
        font-size: 12px;
        margin-top: 5px;
        display: none;
    }
    /* Footer totals styling */
    .footer-totals {
        background-color: #ffffff !important;
        font-weight: bold;
        border-top: 2px solid #000;
    }
    .footer-totals td {
        background-color: #ffffff !important;
        font-weight: bold !important;
        border-top: 2px solid #007bff !important;
        color: #000 !important;
    }
    .total-label {
        color: #000 !important;
        font-size: 14px;
    }
    
    /* Hide DataTables default footer if it exists */
    #daily_sales_payment_report_table tfoot tr:not(.footer-totals) {
        display: none !important;
    }
    
    /* Remove any duplicate footer rows */
    .dataTables_wrapper tfoot {
        background-color: transparent !important;
    }
    
    /* Ensure only our custom footer shows */
    table.dataTable tfoot th,
    table.dataTable tfoot td {
        border-top: none !important;
    }
    
    /* Override any grey backgrounds */
    #daily_sales_payment_report_table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    #daily_sales_payment_report_table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }
</style>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, $location_id, ['class' => 'form-control select2', 'id' => 'sell_list_filter_location_id', 'style' => 'width:100%', 'placeholder' => __('Please Select')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', $date_range, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sell_list_filter_date_range', 'readonly']) !!}
                <div class="date-range-error" id="date-range-error">
                    Date range cannot exceed 1 month (31 days). Please select a shorter period.
                </div>
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
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="daily_sales_payment_report_table" style="width: 100%;">
                        <thead id="table-header">
                            <tr>
                                <th>Date</th>
                                <th>Total Sale Amount</th>
                                <th>Total Invoice</th>
                                @if(!empty($payment_methods))
                                    @foreach($payment_methods as $method)
                                        <th>{{ $payment_method_labels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}</th>
                                    @endforeach
                                @endif
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tfoot id="table-footer" style="display: none;">
                            <tr class="footer-totals">
                                <td class="total-label">Total</td>
                                <td id="footer-total-sale-amount">0.00</td>
                                <td id="footer-total-invoice">0</td>
                                @if(!empty($payment_methods))
                                    @foreach($payment_methods as $method)
                                        <td id="footer-{{ $method }}">0.00</td>
                                    @endforeach
                                @endif
                                <td id="footer-due">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
// Updated JavaScript section for the Blade template
$(document).ready(function() {
    var dailySalesReport;
    var filters_applied = false;
    var currentPaymentMethods = @json($payment_methods);
    var currentPaymentMethodLabels = @json($payment_method_labels);

    // Function to validate date range (max 1 month)
    function validateDateRange(startDate, endDate) {
        var daysDiff = endDate.diff(startDate, 'days') + 1; // +1 to include both start and end dates
        return daysDiff <= 31;
    }

    // Function to show/hide date range error
    function showDateRangeError(show) {
        if (show) {
            $('#date-range-error').show();
            $('#sell_list_filter_date_range').addClass('has-error');
        } else {
            $('#date-range-error').hide();
            $('#sell_list_filter_date_range').removeClass('has-error');
        }
    }

    // Function to format display name for payment methods
    function formatPaymentMethodName(method, customLabels) {
        // Check if we have a custom label for this method
        if (customLabels && customLabels[method]) {
            return customLabels[method];
        }
        
        // Special handling for cash_ring_percentage
        if (method === 'cash_ring_percentage') {
            return 'Cash ring(%)';
        }
        
        // Default formatting for other methods
        return method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    // Function to update table footer with totals
    function updateTableFooter(paymentMethods, paymentMethodLabels) {
        var footerHtml = '<tr class="footer-totals">';
        footerHtml += '<td class="total-label">Total</td>';
        footerHtml += '<td id="footer-total-sale-amount">0.00</td>';
        footerHtml += '<td id="footer-total-invoice">0</td>';
        
        if (paymentMethods && paymentMethods.length > 0) {
            $.each(paymentMethods, function(index, method) {
                footerHtml += '<td id="footer-' + method + '">0.00</td>';
            });
        }
        
        footerHtml += '<td id="footer-due">0.00</td>';
        footerHtml += '</tr>';
        
        $('#table-footer').html(footerHtml);
    }

    // Function to display footer totals
    function displayFooterTotals(footerTotals) {
        if (footerTotals && Object.keys(footerTotals).length > 0) {
            // Show footer
            $('#table-footer').show();
            
            // Update totals
            if (footerTotals.total_sale_amount !== undefined) {
                $('#footer-total-sale-amount').text(__currency_trans_from_en(footerTotals.total_sale_amount, true));
            }
            if (footerTotals.total_invoice !== undefined) {
                $('#footer-total-invoice').text(footerTotals.total_invoice);
            }
            if (footerTotals.due !== undefined) {
                $('#footer-due').text(__currency_trans_from_en(footerTotals.due, true));
            }
            
            // Update payment method totals
            if (footerTotals.payment_methods) {
                $.each(footerTotals.payment_methods, function(method, total) {
                    $('#footer-' + method).text(__currency_trans_from_en(total, true));
                });
            }
        } else {
            // Hide footer if no totals
            $('#table-footer').hide();
        }
    }

    // Initialize date range picker with updated settings
    var dateRangeSettings = {
        startDate: moment().startOf('month'),
        endDate: moment(),
        maxDate: moment().add(1, 'year'), // Allow future dates up to 1 year
        minDate: moment().subtract(5, 'years'), // Allow past dates up to 5 years back
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
            'Next 7 Days': [moment(), moment().add(6, 'days')],
            'Next 30 Days': [moment(), moment().add(29, 'days')]
        },
        // Custom validation function
        isInvalidDate: function(date) {
            // Allow all dates within the min/max range
            return false;
        }
    };

    // Initialize date range picker
    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            var format = moment_date_format || 'YYYY-MM-DD';
            
            // Validate date range before applying
            if (!validateDateRange(start, end)) {
                showDateRangeError(true);
                // Reset to previous valid range or default
                var defaultStart = moment().startOf('month');
                var defaultEnd = moment();
                $('#sell_list_filter_date_range').data('daterangepicker').setStartDate(defaultStart);
                $('#sell_list_filter_date_range').data('daterangepicker').setEndDate(defaultEnd);
                $('#sell_list_filter_date_range').val(defaultStart.format(format) + ' ~ ' + defaultEnd.format(format));
                
                // Show error message
                toastr.error('Date range cannot exceed 1 month (31 days). Please select a shorter period.');
                return false;
            } else {
                showDateRangeError(false);
                $('#sell_list_filter_date_range').val(start.format(format) + ' ~ ' + end.format(format));
            }
        }
    );

    // Add event listener for date range picker show event
    $('#sell_list_filter_date_range').on('show.daterangepicker', function(ev, picker) {
        showDateRangeError(false);
    });

    // Add event listener for date range picker apply event
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        var startDate = picker.startDate;
        var endDate = picker.endDate;
        
        if (!validateDateRange(startDate, endDate)) {
            ev.preventDefault();
            showDateRangeError(true);
            toastr.error('Date range cannot exceed 1 month (31 days). Please select a shorter period.');
            return false;
        }
        
        showDateRangeError(false);
    });

    // Function to initialize or reinitialize DataTable
    function initializeDataTable(paymentMethods, paymentMethodLabels) {
        if (dailySalesReport) {
            dailySalesReport.destroy();
        }

        // Update current payment methods and labels
        currentPaymentMethods = paymentMethods || [];
        currentPaymentMethodLabels = paymentMethodLabels || {};

        // Build columns dynamically
        var columns = [
            { data: 'date', name: 'date', title: 'Date' },
            { data: 'total_sale_amount', name: 'total_sale_amount', title: 'Total Sale Amount', 
              render: function(data, type, row) {
                  return data ? '<span class="display_currency" data-orig-value="' + data + '">' + data + '</span>' : '0.00';
              } 
            },
            { data: 'total_invoice', name: 'total_invoice', title: 'Total Invoice' }
        ];

        // Add dynamic payment method columns
        if (currentPaymentMethods && currentPaymentMethods.length > 0) {
            $.each(currentPaymentMethods, function(index, method) {
                // Use the custom label if available, otherwise use the formatPaymentMethodName function
                var displayName = currentPaymentMethodLabels[method] || formatPaymentMethodName(method, currentPaymentMethodLabels);
                
                columns.push({ 
                    data: method, 
                    name: method,
                    title: displayName,
                    render: function(data, type, row) {
                        return data ? '<span class="display_currency" data-orig-value="' + data + '">' + data + '</span>' : '0.00';
                    }
                });
            });
        }

        // Add Due column at the end
        columns.push({
            data: 'due', 
            name: 'due', 
            title: 'Due',
            render: function(data, type, row) {
                return data ? '<span class="display_currency" data-orig-value="' + data + '">' + data + '</span>' : '0.00';
            }
        });

        // Update table header and footer dynamically
        updateTableHeader(currentPaymentMethods, currentPaymentMethodLabels);
        updateTableFooter(currentPaymentMethods, currentPaymentMethodLabels);

        dailySalesReport = $('#daily_sales_payment_report_table').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            ajax: {
                url: "{{ route('reports.daily-sales-payment-report') }}",
                type: 'GET',
                data: function(d) {
                    d.sell_list_filter_location_id = $('#sell_list_filter_location_id').val();
                    d.sell_list_filter_date_range = $('#sell_list_filter_date_range').val();
                    d.ajax = 1;
                    d.apply_filters = filters_applied ? '1' : '0';
                },
                dataSrc: function(json) {
                    // Update payment methods and labels from server response
                    if (json.payment_methods) {
                        currentPaymentMethods = json.payment_methods;
                    }
                    if (json.payment_method_labels) {
                        currentPaymentMethodLabels = json.payment_method_labels;
                    }
                    
                    // Display footer totals
                    if (json.footer_totals) {
                        displayFooterTotals(json.footer_totals);
                    }
                    
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    console.log('AJAX Error: ', xhr.responseText);
                    if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                        toastr.error(xhr.responseJSON.error);
                    } else {
                        toastr.error('An error occurred while loading the report.');
                    }
                    $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                }
            },
            columns: columns,
            language: {
                emptyTable: filters_applied ? "No data available for the selected filters" : "Please select location and date range, then click Apply Filters to load data",
                zeroRecords: "No matching records found for the selected filters."
            },
            order: [[0, 'desc']],
            footerCallback: function (row, data, start, end, display) {
                // Disable DataTables default footer callback to prevent conflicts
                return;
            },
            drawCallback: function(settings) {
                // Remove any unwanted grey rows or duplicate footers after each draw
                $('#daily_sales_payment_report_table tfoot tr:not(.footer-totals)').remove();
            }
        });
    }

    // Function to update table header
    function updateTableHeader(paymentMethods, paymentMethodLabels) {
        var headerHtml = '<tr><th>Date</th><th>Total Sale Amount</th><th>Total Invoice</th>';
        if (paymentMethods && paymentMethods.length > 0) {
            $.each(paymentMethods, function(index, method) {
                var displayName = paymentMethodLabels[method] || formatPaymentMethodName(method, paymentMethodLabels);
                headerHtml += '<th>' + displayName + '</th>';
            });
        }
        headerHtml += '<th>Due</th></tr>';
        $('#table-header').html(headerHtml);
    }

    // Initialize DataTable with initial payment methods
    initializeDataTable(currentPaymentMethods, currentPaymentMethodLabels);

    // Handle location change
    $('#sell_list_filter_location_id').on('change', function() {
        filters_applied = false;
        $('#table-footer').hide(); // Hide footer when location changes
        if (dailySalesReport) {
            dailySalesReport.ajax.reload(null, false);
        }
    });

    // Apply filters button click
    $('#apply_filters_btn').click(function() {
        var location_id = $('#sell_list_filter_location_id').val();
        var date_range = $('#sell_list_filter_date_range').val();

        if (!location_id || location_id === '') {
            toastr.error('{{ __("Please select a location") }}');
            return;
        }
        
        if (!date_range || date_range === '') {
            toastr.error('{{ __("Please select a date range") }}');
            return;
        }

        // Additional validation for date range before submitting
        if (date_range) {
            var dates = date_range.split(' ~ ');
            if (dates.length === 2) {
                var format = moment_date_format || 'YYYY-MM-DD';
                var startDate = moment(dates[0], format);
                var endDate = moment(dates[1], format);
                
                if (!validateDateRange(startDate, endDate)) {
                    toastr.error('Date range cannot exceed 1 month (31 days). Please select a shorter period.');
                    showDateRangeError(true);
                    return;
                }
            }
        }

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Loading...") }}');

        filters_applied = true;

        $.ajax({
            url: "{{ route('reports.daily-sales-payment-report') }}",
            type: 'GET',
            data: {
                sell_list_filter_location_id: location_id,
                ajax: 1,
                apply_filters: '1',
                sell_list_filter_date_range: date_range
            },
            success: function(response) {
                console.log('Server response:', response); // Debug log
                var paymentMethods = response.payment_methods || [];
                var paymentMethodLabels = response.payment_method_labels || {};
                var footerTotals = response.footer_totals || {};
                
                console.log('Payment methods:', paymentMethods); // Debug log
                console.log('Payment method labels:', paymentMethodLabels); // Debug log
                console.log('Footer totals:', footerTotals); // Debug log
                
                initializeDataTable(paymentMethods, paymentMethodLabels);
                
                // Display footer totals
                displayFooterTotals(footerTotals);
                
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
            },
            error: function(xhr, error, thrown) {
                console.log('AJAX Error: ', xhr.responseText);
                if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                    toastr.error(xhr.responseJSON.error);
                } else {
                    toastr.error('An error occurred while loading the report.');
                }
                filters_applied = false;
                $('#table-footer').hide(); // Hide footer on error
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
            }
        });
    });

    // Helper function for currency formatting (if not already available)
    if (typeof __currency_trans_from_en === 'undefined') {
        window.__currency_trans_from_en = function(amount, show_symbol) {
            // Fallback currency formatting
            var formatted = parseFloat(amount).toFixed(2);
            return show_symbol ? '$ ' + formatted : formatted;
        };
    }
});
</script>
@endsection