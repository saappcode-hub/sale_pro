@extends('layouts.app')
@section('title', __('Sales Report Summary'))

@section('content')

<section class="content-header">
    <h1>{{ __('Sales Report Summary')}}</h1>
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
        display: none; /* Hidden by default */
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

    .footer-total td {
        font-weight: bold;
    }
    #sales_report_summary_table th:nth-child(n+2),
    #sales_report_summary_table td:nth-child(n+2) {
        text-align: center;
    }

    /* DataTables inline layout styling */
    .dataTables_wrapper .col-sm-12 {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 10px;
    }

    /* Length menu styling */
    .dataTables_wrapper .dataTables_length {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }

    .dataTables_wrapper .dataTables_length label {
        margin-bottom: 0;
        display: flex;
        align-items: center;
        white-space: nowrap;
    }

    .dataTables_wrapper .dataTables_length select {
        display: inline-block;
        width: auto;
        margin: 0 5px;
    }

    /* Buttons container styling - center them */
    .dataTables_wrapper .dt-buttons {
        display: inline-flex;
        margin: 0 auto;
        flex-shrink: 0;
    }

    .dataTables_wrapper .dt-button {
        padding: 5px 10px;
        font-size: 12px;
        margin: 0 2px;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        color: #333;
        line-height: 1.5;
        white-space: nowrap;
    }

    .dataTables_wrapper .dt-button:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }

    /* Search/filter styling */
    .dataTables_wrapper .dataTables_filter {
        display: inline-flex;
        align-items: center;
        margin-left: auto;
        flex-shrink: 0;
    }

    .dataTables_wrapper .dataTables_filter label {
        margin-bottom: 0;
        white-space: nowrap;
    }

    .dataTables_wrapper .dataTables_filter input {
        display: inline-block;
        width: auto;
        margin-left: 5px;
    }

    /* Remove any default margins that might break the layout */
    .dataTables_wrapper .row:first-child {
        margin-bottom: 10px;
    }

  
</style>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!!
                Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!!
                Form::select('sell_list_filter_location_id', $business_locations, $location_id ?? '', ['class' => 'form-control select2', 'id' => 'sell_list_filter_location_id', 'style' => 'width:100%', 'placeholder' => __('Please Select')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!!
                Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!!
                Form::text('sell_list_filter_date_range', $date_range ?? '', ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sell_list_filter_date_range', 'readonly']) !!}
                <div class="date-range-error" id="date-range-error">
                    Date range cannot exceed 1 month (31 days).
                    Please select a shorter period.
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
            <div class="box">
                <div class="box-body">
                    <div class="table-responsive">
                        <table 
                        class="table table-bordered table-striped" 
                        id="sales_report_summary_table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>@lang('Product Name')</th>
                                    <th>@lang('Sales Qty')</th>
                                    <th>@lang('Sales Amount')</th>
                                    <th>@lang('Reward Out Qty')</th>
                                    <th>@lang('Reward Out Amount')</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr class="bg-gray font-17 footer-total">
                                    <td><strong>@lang('Total'):</strong></td>
                                    <td style="text-align: center;">-</td>
                                    <td style="text-align: center;"></td>
                                    <td style="text-align: center;">-</td>
                                    <td style="text-align: center;"></td>
                                </tr>
                                <tr class="bg-gray font-17 footer-total">
                                    <td><strong>@lang('Total Amount'):</strong></td>
                                    <td colspan="4" style="text-align: left;" id="total-amount-footer"></td>
                                </tr>
                                </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script type="text/javascript">
$(document).ready(function() {
    var salesReportTable;
    var filters_applied = false;

    // Function to clear all error states
    function clearErrorStates() {
        showDateRangeError(false);
        $('#sell_list_filter_location_id').removeClass('has-error');
    }

    // Function to validate date range (max 1 month)
    function validateDateRange(startDate, endDate) {
        var daysDiff = endDate.diff(startDate, 'days') + 1;
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

    // Function to format currency consistently
    function formatCurrency(number) {
        return '$' + parseFloat(number).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Function to strip HTML and clean text
    function stripHtml(html) {
        var tmp = document.createElement("DIV");
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || "";
    }

    // Initialize date range picker
    var dateRangeSettings = {
        startDate: moment().startOf('month'),
        endDate: moment(),
        maxDate: moment().add(1, 'year'),
        minDate: moment().subtract(5, 'years'),
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
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    };

    // Initialize date range picker
    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            var format = moment_date_format || 'YYYY-MM-DD';
            
            if (!validateDateRange(start, end)) {
                showDateRangeError(true);
                var defaultStart = moment().startOf('month');
                var defaultEnd = moment();
                $('#sell_list_filter_date_range').data('daterangepicker').setStartDate(defaultStart);
                $('#sell_list_filter_date_range').data('daterangepicker').setEndDate(defaultEnd);
                $('#sell_list_filter_date_range').val(defaultStart.format(format) + ' ~ ' + defaultEnd.format(format));
                toastr.error('Date range cannot exceed 1 month (31 days). Please select a shorter period.');
                return false;
            } else {
                showDateRangeError(false);
                $('#sell_list_filter_date_range').val(start.format(format) + ' ~ ' + end.format(format));
            }
        }
    );

    // Initialize DataTable with Buttons extension
    salesReportTable = $('#sales_report_summary_table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        deferRender: true,
        dom: '<"row"<"col-sm-12"lBf>>rtip',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="fa fa-file-text-o"></i> Export to CSV',
                title: 'Sales Report Summary',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4],
                    format: {
                        body: function(data, row, column, node) {
                            // Strip HTML tags
                            var cleanData = stripHtml(data);
                            // Remove currency symbols for amount columns
                            if (column === 2 || column === 4) {
                                cleanData = cleanData.replace('$', '').replace(/,/g, '').trim();
                            }
                            return cleanData;
                        }
                    }
                },
                action: function (e, dt, button, config) {
                    // Get current data
                    var data = dt.buttons.exportData(config.exportOptions);
                    var api = dt;
                    var json = api.ajax.json();
                    
                    // Calculate totals from visible columns
                    var total_sales_amount = 0;
                    var total_reward_out_amount = 0;
                    
                    // Sum from the raw amount columns in the table
                    api.rows({page: 'current'}).every(function() {
                        var rowData = this.data();
                        total_sales_amount += parseFloat(rowData.raw_sales_amount || 0);
                        total_reward_out_amount += parseFloat(rowData.raw_reward_out_amount || 0);
                    });
                    
                    var combined_total = total_sales_amount + total_reward_out_amount;
                    
                    // Add footer rows to CSV data
                    data.body.push(['Total', '-', total_sales_amount.toFixed(2), '-', total_reward_out_amount.toFixed(2)]);
                    data.body.push(['Total Amount', combined_total.toFixed(2), '', '', '']);
                    
                    // Add payment data if available
                    if (json && json.due_amount && parseFloat(json.due_amount) > 0) {
                        data.body.push(['Due', parseFloat(json.due_amount).toFixed(2), '', '', '']);
                    }
                    
                    if (json && json.payment_methods && json.payment_methods.length > 0) {
                        json.payment_methods.forEach(function(method) {
                            var amount = parseFloat(json.payment_totals[method]) || 0;
                            if (amount > 0) {
                                var label = json.payment_labels[method] || method;
                                data.body.push([label, amount.toFixed(2), '', '', '']);
                            }
                        });
                    }
                    
                    // Use default CSV export with modified data
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                }
            },
            {
    extend: 'excelHtml5',
    text: '<i class="fa fa-file-excel-o"></i> Export to Excel',
    title: 'Sales Report Summary',
    exportOptions: {
        columns: [0, 1, 2, 3, 4],
        format: {
            // This part is crucial: it must export clean numbers so the
            // customize function can perform calculations before formatting.
            body: function(data, row, column, node) {
                var cleanData = stripHtml(data);
                if (column === 2 || column === 4) {
                    cleanData = cleanData.replace('$', '').replace(/,/g, '').trim();
                    return parseFloat(cleanData) || 0;
                }
                return cleanData;
            }
        }
    },
    customize: function(xlsx) {
        var sheet = xlsx.xl.worksheets['sheet1.xml'];
        var api = salesReportTable;
        var json = api.ajax.json();

        // **FIX START: Manually add '$' prefix and set cell type to string**

        // Helper function to format a number into a "$0.00" string
        var formatAsString = function(num) {
            return '$' + parseFloat(num).toFixed(2);
        };

        // 1. Reformat the main data rows
        // We find the amount columns and change their content from a number to a formatted string.
        $('row:not([r="1"])', sheet).each(function() {
            // Reformat 'Sales Amount' column (C)
            var salesCell = $(this).find('c[r^="C"]');
            var salesVal = parseFloat(salesCell.text());
            if (!isNaN(salesVal)) {
                salesCell.attr('t', 'str'); // Set the cell type to string
                salesCell.find('v').text(formatAsString(salesVal)); // Set the new formatted value
            }

            // Reformat 'Reward Out Amount' column (E)
            var rewardCell = $(this).find('c[r^="E"]');
            var rewardVal = parseFloat(rewardCell.text());
            if (!isNaN(rewardVal)) {
                rewardCell.attr('t', 'str'); // Set the cell type to string
                rewardCell.find('v').text(formatAsString(rewardVal)); // Set the new formatted value
            }
        });
        
        // **FIX END**

        // 2. Calculate totals (this part is unchanged)
        var total_sales_amount = 0;
        var total_reward_out_amount = 0;
        
        api.rows({page: 'current'}).every(function() {
            var rowData = this.data();
            total_sales_amount += parseFloat(rowData.raw_sales_amount || 0);
            total_reward_out_amount += parseFloat(rowData.raw_reward_out_amount || 0);
        });
        
        var combined_total = total_sales_amount + total_reward_out_amount;
        
        // 3. Build the footer with the new string formatting
        var numrows = $('row', sheet).length;
        var currentRow = numrows + 1;
        var footerXML = '';
        
        // Total row
        footerXML += '<row r="' + currentRow + '">';
        footerXML += '<c r="A' + currentRow + '" t="str"><v>Total</v></c>';
        footerXML += '<c r="B' + currentRow + '" t="str"><v>-</v></c>';
        // **FIX: Format amount as a string with '$'**
        footerXML += '<c r="C' + currentRow + '" t="str"><v>' + formatAsString(total_sales_amount) + '</v></c>';
        footerXML += '<c r="D' + currentRow + '" t="str"><v>-</v></c>';
        // **FIX: Format amount as a string with '$'**
        footerXML += '<c r="E' + currentRow + '" t="str"><v>' + formatAsString(total_reward_out_amount) + '</v></c>';
        footerXML += '</row>';
        currentRow++;
        
        // Total Amount row
        footerXML += '<row r="' + currentRow + '">';
        footerXML += '<c r="A' + currentRow + '" t="str"><v>Total Amount</v></c>';
        // **FIX: Format amount as a string with '$'**
        footerXML += '<c r="B' + currentRow + '" t="str"><v>' + formatAsString(combined_total) + '</v></c>';
        footerXML += '</row>';
        currentRow++;
        
        // Add payment data rows with string formatting
        if (json) {
            if (json.due_amount && parseFloat(json.due_amount) > 0) {
                footerXML += '<row r="' + currentRow + '">';
                footerXML += '<c r="A' + currentRow + '" t="str"><v>Due</v></c>';
                footerXML += '<c r="B' + currentRow + '" t="str"><v>' + formatAsString(json.due_amount) + '</v></c>';
                footerXML += '</row>';
                currentRow++;
            }
            
            if (json.payment_methods && json.payment_methods.length > 0) {
                json.payment_methods.forEach(function(method) {
                    var amount = parseFloat(json.payment_totals[method]) || 0;
                    if (amount > 0) {
                        var label = json.payment_labels[method] || method;
                        footerXML += '<row r="' + currentRow + '">';
                        footerXML += '<c r="A' + currentRow + '" t="str"><v>' + label + '</v></c>';
                        footerXML += '<c r="B' + currentRow + '" t="str"><v>' + formatAsString(amount) + '</v></c>';
                        footerXML += '</row>';
                        currentRow++;
                    }
                });
            }
        }
        
        // 4. Append the generated footer to the sheet
        $('sheetData', sheet).append(footerXML);
    }
},
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> Print',
                title: 'Sales Report Summary',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                },
                customize: function(win) {
                    var api = salesReportTable;
                    var json = api.ajax.json();
                    
                    // Calculate totals
                    var total_sales_amount = 0;
                    var total_reward_out_amount = 0;
                    
                    api.rows({page: 'current'}).every(function() {
                        var rowData = this.data();
                        total_sales_amount += parseFloat(rowData.raw_sales_amount || 0);
                        total_reward_out_amount += parseFloat(rowData.raw_reward_out_amount || 0);
                    });
                    
                    var combined_total = total_sales_amount + total_reward_out_amount;
                    
                    // Find the table in the print view, and add a tfoot to it
                    var table = $(win.document.body).find('table');
                    table.append('<tfoot></tfoot>');

                    // Build the footer rows as an HTML string
                    var footerHtml = '';
                    
                    // Total row
                    footerHtml += '<tr>';
                    footerHtml += '<td><strong>Total:</strong></td>';
                    footerHtml += '<td style="text-align:center;">-</td>';
                    footerHtml += '<td style="text-align:center;">' + formatCurrency(total_sales_amount) + '</td>';
                    footerHtml += '<td style="text-align:center;">-</td>';
                    footerHtml += '<td style="text-align:center;">' + formatCurrency(total_reward_out_amount) + '</td>';
                    footerHtml += '</tr>';
                    
                    // Total Amount row
                    footerHtml += '<tr>';
                    footerHtml += '<td><strong>Total Amount:</strong></td>';
                    footerHtml += '<td colspan="4" style="text-align:left;">' + formatCurrency(combined_total) + '</td>';
                    footerHtml += '</tr>';
                    
                    // Add payment data rows
                    if (json) {
                        if (json.due_amount && parseFloat(json.due_amount) > 0) {
                            footerHtml += '<tr>';
                            footerHtml += '<td><strong>Due:</strong></td>';
                            footerHtml += '<td colspan="4" style="text-align:left;">' + formatCurrency(parseFloat(json.due_amount)) + '</td>';
                            footerHtml += '</tr>';
                        }
                        
                        if (json.payment_methods && json.payment_methods.length > 0) {
                            json.payment_methods.forEach(function(method) {
                                var amount = parseFloat(json.payment_totals[method]) || 0;
                                if (amount > 0) {
                                    var label = json.payment_labels[method] || method;
                                    footerHtml += '<tr>';
                                    footerHtml += '<td><strong>' + label + ':</strong></td>';
                                    footerHtml += '<td colspan="4" style="text-align:left;">' + formatCurrency(amount) + '</td>';
                                    footerHtml += '</tr>';
                                }
                            });
                        }
                    }
                    
                    // Append the footer rows to the tfoot in the print view table
                    $(win.document.body).find('table tfoot').append(footerHtml);

                    // Optional: Add some basic styling for the footer in the print view
                     $(win.document.head).append(
                        '<style>' +
                        'tfoot tr td { font-weight: bold; }' +
                        '</style>'
                    );
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-columns"></i> Column visibility'
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fa fa-file-pdf-o"></i> Export to PDF',
                title: 'Sales Report Summary',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4],
                    format: {
                        body: function(data, row, column, node) {
                            return stripHtml(data);
                        }
                    }
                },
                customize: function(doc) {
                    var api = salesReportTable;
                    var json = api.ajax.json();
                    
                    // Calculate totals
                    var total_sales_amount = 0;
                    var total_reward_out_amount = 0;
                    
                    api.rows({page: 'current'}).every(function() {
                        var rowData = this.data();
                        total_sales_amount += parseFloat(rowData.raw_sales_amount || 0);
                        total_reward_out_amount += parseFloat(rowData.raw_reward_out_amount || 0);
                    });
                    
                    var combined_total = total_sales_amount + total_reward_out_amount;
                    
                    // Add footer table
                    var footerTable = {
                        table: {
                            widths: ['*', 'auto', 'auto', 'auto', 'auto'],
                            body: [
                                [
                                    {text: 'Total:', bold: true},
                                    {text: '-', alignment: 'center'},
                                    {text: formatCurrency(total_sales_amount), alignment: 'right'},
                                    {text: '-', alignment: 'center'},
                                    {text: formatCurrency(total_reward_out_amount), alignment: 'right'}
                                ],
                                [
                                    {text: 'Total Amount:', bold: true},
                                    {text: formatCurrency(combined_total), colSpan: 4, alignment: 'center'},
                                    '', '', ''
                                ]
                            ]
                        },
                        margin: [0, 10, 0, 0]
                    };
                    
                    // Add payment data
                    if (json) {
                        if (json.due_amount && parseFloat(json.due_amount) > 0) {
                            footerTable.table.body.push([
                                {text: 'Due:', bold: true},
                                {text: formatCurrency(parseFloat(json.due_amount)), colSpan: 4, alignment: 'center'},
                                '', '', ''
                            ]);
                        }
                        
                        if (json.payment_methods && json.payment_methods.length > 0) {
                            json.payment_methods.forEach(function(method) {
                                var amount = parseFloat(json.payment_totals[method]) || 0;
                                if (amount > 0) {
                                    var label = json.payment_labels[method] || method;
                                    footerTable.table.body.push([
                                        {text: label + ':', bold: true},
                                        {text: formatCurrency(amount), colSpan: 4, alignment: 'center'},
                                        '', '', ''
                                    ]);
                                }
                            });
                        }
                    }
                    
                    doc.content.push(footerTable);
                }
            }
        ],
        ajax: {
            url: "{{ route('reports.sales-report-summary') }}",
            type: 'GET',
            data: function(d) {
                var location_id = $('#sell_list_filter_location_id').val();
                var date_range = $('#sell_list_filter_date_range').val();
                
                d.sell_list_filter_location_id = location_id;
                d.sell_list_filter_date_range = date_range;
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
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> Apply Filters');
            }
        },
        columns: [
            { data: 'product_name', name: 'product_name' },
            { data: 'sales_qty', name: 'sales_qty', searchable: false, orderable: false },
            { data: 'sales_amount', name: 'sales_amount', searchable: false, orderable: false },
            { data: 'reward_out_qty', name: 'reward_out_qty', searchable: false, orderable: false },
            { data: 'reward_out_amount', name: 'reward_out_amount', searchable: false, orderable: false },
            { data: 'raw_sales_qty', name: 'raw_sales_qty', visible: false },
            { data: 'raw_sales_amount', name: 'raw_sales_amount', visible: false },
            { data: 'raw_reward_out_qty', name: 'raw_reward_out_qty', visible: false },
            { data: 'raw_reward_out_amount', name: 'raw_reward_out_amount', visible: false }
        ],
        "footerCallback": function (row, data, start, end, display) {
            var api = this.api();
            var json = api.ajax.json();

            var sumColumn = function(colIndex) {
                return api
                    .column(colIndex, { page: 'current' })
                    .data()
                    .reduce(function (a, b) {
                        return parseFloat(a || 0) + parseFloat(b || 0);
                    }, 0);
            };

            // Calculate amount totals
            var total_sales_amount = sumColumn(6);
            var total_reward_out_amount = sumColumn(8);
            var combined_total = total_sales_amount + total_reward_out_amount;

            var footer = $(api.table().footer());

            // Update first footer row (Totals)
            footer.find('tr:eq(0) td:eq(1)').html('-');
            footer.find('tr:eq(0) td:eq(2)').html(formatCurrency(total_sales_amount));
            footer.find('tr:eq(0) td:eq(3)').html('-');
            footer.find('tr:eq(0) td:eq(4)').html(formatCurrency(total_reward_out_amount));
            
            // Update Total Amount row
            footer.find('tr:eq(1) td:eq(1)').html(formatCurrency(combined_total));

            // Remove existing payment and due rows
            footer.find('tr.payment-method-row, tr.due-row').remove();

            // Add Due row first (right after Total Amount)
            if (json && typeof json.due_amount !== 'undefined') {
                var dueAmount = parseFloat(json.due_amount) || 0;
                
                if (dueAmount > 0) {
                    var dueRow = '<tr class="bg-gray font-17 footer-total due-row">' +
                        '<td><strong>Due:</strong></td>' +
                        '<td colspan="4" style="text-align: left;">' + formatCurrency(dueAmount) + '</td>' +
                        '</tr>';
                    
                    footer.append(dueRow);
                }
            }

            // Add payment method rows if available
            if (json && json.payment_methods && json.payment_methods.length > 0) {
                json.payment_methods.forEach(function(method) {
                    var amount = parseFloat(json.payment_totals[method]) || 0;
                    
                    if (amount > 0) {
                        var label = json.payment_labels[method] || method;
                        
                        var paymentRow = '<tr class="bg-gray font-17 footer-total payment-method-row">' +
                            '<td><strong>' + label + ':</strong></td>' +
                            '<td colspan="4" style="text-align: left;">' + formatCurrency(amount) + '</td>' +
                            '</tr>';
                        
                        footer.append(paymentRow);
                    }
                });
            }
        },
        language: {
            emptyTable: "Please select location and date range, then click Apply Filters to load data",
            zeroRecords: "No matching records found for the selected filters.",
            processing: "Loading sales report data..."
        },
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        searching: true,
        search: {
            smart: true,
            regex: false,
            caseInsensitive: true
        }
    });

    // Event handlers
    $('#sell_list_filter_location_id').on('change', function() {
        clearErrorStates();
        filters_applied = false;
    });
    
    $('#sell_list_filter_date_range').on('show.daterangepicker', function(ev, picker) {
        clearErrorStates();
    });
    
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
        filters_applied = false;
    });

    // APPLY FILTERS BUTTON
    $('#apply_filters_btn').click(function() {
        var location_id = $('#sell_list_filter_location_id').val();
        var date_range = $('#sell_list_filter_date_range').val();
        var hasErrors = false;

        showDateRangeError(false);
        $('#sell_list_filter_location_id').removeClass('has-error');

        // Validate location
        if (!location_id || location_id === '') {
            $('#sell_list_filter_location_id').addClass('has-error');
            toastr.error('Please select a business location');
            hasErrors = true;
        }
        
        // Validate date range
        if (!date_range || date_range === '') {
            showDateRangeError(true);
            toastr.error('Please select a date range');
            hasErrors = true;
        }

        if (hasErrors) {
            return;
        }

        // Validate date range format and duration
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

                if (!startDate.isValid() || !endDate.isValid()) {
                    toastr.error('Invalid date format. Please select a valid date range.');
                    showDateRangeError(true);
                    return;
                }
            } else {
                toastr.error('Please select a valid date range.');
                showDateRangeError(true);
                return;
            }
        }

        // All validations passed - proceed with loading data
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        // Set the flag to allow data loading
        filters_applied = true;
        // Reload the sales report table
        salesReportTable.ajax.reload(function() {
            $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> Apply Filters');
            
            var info = salesReportTable.page.info();
            if (info.recordsTotal > 0) {
                toastr.success('Sales report summary loaded successfully. Found ' + 
                info.recordsTotal + ' records.');
            } else {
                toastr.info('No records found for the selected criteria.');
            }
        }, false);
    });
});
</script>
@endsection