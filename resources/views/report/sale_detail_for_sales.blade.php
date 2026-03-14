@extends('layouts.app')
@section('title', __('Sales Details Report (Sellers)'))

@section('content')

{{-- Style for the table footer --}}
<style>
    .footer-total {
        background-color: #d2d6de !important;
    }
    .footer-total td, .footer-total strong {
        color: black !important;
    }
</style>

<section class="content-header">
    <h1>{{ __('Sales Details Report (Sellers)')}}</h1>
</section>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('search_product', __('lang_v1.search_product') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-search"></i>
                    </span>
                    <input type="hidden" value="" id="variation_id">
                    {!! Form::text('search_product', null, ['class' => 'form-control', 'id' => 'search_product', 'placeholder' => __('lang_v1.search_product_placeholder')]); !!}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-user"></i>
                    </span>
                    {!! Form::select('customer_id', $customers, null, ['class' => 'form-control select2', 'placeholder' => __('All'), 'id' => 'customer_id']); !!}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('customer_group_id', __( 'lang_v1.customer_group_name' ) . ':') !!}
                {!! Form::select('customer_group_id', $customer_group, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'customer_group_id', 'placeholder' => __('All')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, $location_id ?? '', ['class' => 'form-control select2', 'id' => 'sell_list_filter_location_id', 'style' => 'width:100%', 'placeholder' => __('All')]) !!}
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('category_id', __('product.category') . ':') !!}
                {!! Form::select('category_id', $categories, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_category_id', 'placeholder' => __('All')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('brand_id', __('product.brand') . ':') !!}
                {!! Form::select('brand_id', $brands, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'filter_brand_id', 'placeholder' => __('All')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', $date_range ?? '', ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sell_list_filter_date_range', 'readonly']) !!}
                <div class="date-range-error" id="date-range-error" style="display: none; color: red;">
                    Date range cannot exceed 3 months (90 days). Please select a shorter period.
                </div>
            </div>
        </div>
        <div class="col-md-3 apply-btn-container" style="margin-top: 25px;">
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
                    <table class="table table-bordered table-striped" 
                    id="sell_detail_report_table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>@lang('Date')</th>
                                <th>@lang('Customer Name')</th>
                                <th>@lang('Contact ID')</th>
                                <th>@lang('Order No')</th> 
                                <th>@lang('Invoice No')</th>
                                <th>Product</th>
                                <th>@lang('Sku')</th>
                                <th>@lang('Qty')</th>
                                <th>Unit</th>
                                <th>@lang('Delivery Person')</th> {{-- Added --}}
                                <th>@lang('Shipping Status')</th>
                                <th>@lang('Payment Status')</th>
                                <th>@lang('Remark')</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr class="footer-total">
                                <td colspan="7" class="footer-total-label" style="text-align: center;"> <strong>TOTAL:</strong>
                                </td>
                                <td class="text-right" id="footer-qty">
                                    <strong>0.00</strong>
                                </td>
                                <td></td> 
                                <td colspan="4"></td> {{-- Adjusted colspan --}}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
<div class="modal fade view_register" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
@endsection
@section('javascript')
<script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
    var salesDetailReport;
    var filters_applied = false;

    function clearErrorStates() {
        showDateRangeError(false);
        $('#sell_list_filter_location_id').removeClass('has-error');
    }

    function validateDateRange(startDate, endDate) {
        var daysDiff = endDate.diff(startDate, 'days') + 1;
        return daysDiff <= 90;
    }

    function showDateRangeError(show) {
        if (show) {
            $('#date-range-error').show();
            $('#sell_list_filter_date_range').addClass('has-error');
        } else {
            $('#date-range-error').hide();
            $('#sell_list_filter_date_range').removeClass('has-error');
        }
    }

    function updateFooterTotals(data) {
        if (data && data.totalQuantity !== undefined) {
            $('#footer-qty').html('<strong>' + data.totalQuantity + '</strong>');
        } else {
            $('#footer-qty').html('<strong>0.00</strong>');
        }
    }

    function getAllDataForExport(callback) {
        var params = {
            sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
            sell_list_filter_date_range: $('#sell_list_filter_date_range').val(),
            variation_id: $('#variation_id').val(),
            customer_id: $('#customer_id').val(),
            customer_group_id: $('#customer_group_id').val(),
            category_id: $('#filter_category_id').val(),
            brand_id: $('#filter_brand_id').val(),
            apply_filters: filters_applied ? '1' : '0',
            length: -1,
            start: 0,
            ajax: 1
        };
        
        if (salesDetailReport) {
            params.dt_search = salesDetailReport.search();
        }
        
        $.ajax({
            url: "{{action([\App\Http\Controllers\ReportController::class, 'SaleDetailReportForSell'])}}",
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

    function processDataForExport(data) {
        if (!data || data.length === 0) {
            return [];
        }

        var processedData = [];
    
        data.forEach(function(row) {
            var rowData = [
                row.transaction_date || '',
                row.customer_name || '',
                row.contact_id || '',
                row.order_no || '-',
                row.invoice_no || '',
                row.product_name || '',
                row.sku || '',
                parseFloat(row.quantity || 0).toFixed(2),
                row.unit || '',
                row.delivery_person_name || '-', // Added
                row.shipping_status || '',
                row.payment_status || '',
                (row.remark || '').replace(/<br>/g, '\n').replace(/&nbsp;/g, ' ')
            ];

            processedData.push(rowData);
        });
        
        return processedData;
    }
    
    function setupCustomExportButtons() {
        setTimeout(function() {
            // Updated Headers (13 cols)
            var headers = ['Date', 'Customer Name', 'Contact ID', 'Order No', 'Invoice No', 'Product', 'Sku', 'Qty', 'Unit', 'Delivery Person', 'Shipping Status', 'Payment Status', 'Remark'];

            $('.buttons-csv').off('click').on('click', function(e) {
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
                    
                    var processedData = processDataForExport(data);
                    
                    var csvContent = [];
                    csvContent.push(headers.join(','));
                    
                    processedData.forEach(function(row) {
                        var values = row.map(function(value) {
                            var stringValue = String(value || '');
                            if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
                                stringValue = '"' + stringValue.replace(/"/g, '""') + '"';
                            }
                            return stringValue;
                        });
                        csvContent.push(values.join(','));
                    });
                    
                    var blob = new Blob([csvContent.join('\n')], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    var url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'sales_detail_report_sellers.csv');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    toastr.success('CSV export completed successfully');
                });
            });
            
            $('.buttons-excel').off('click').on('click', function(e) {
                e.preventDefault();
                
                if (!filters_applied) {
                    toastr.warning('Please apply filters first to export data');
                    return;
                }
                
                if (typeof XLSX === 'undefined') {
                    toastr.error('Excel export library not loaded');
                    return;
                }
                
                getAllDataForExport(function(data) {
                    if (data.length === 0) {
                        toastr.warning('No data to export');
                        return;
                    }
                    
                    var processedData = processDataForExport(data);
                    var worksheetData = [headers].concat(processedData);
                    
                    var wb = XLSX.utils.book_new();
                    var ws = XLSX.utils.aoa_to_sheet(worksheetData);
                    
                    // Column widths (13 cols)
                    ws['!cols'] = [
                        {wch: 15}, {wch: 25}, {wch: 12}, {wch: 15}, {wch: 15}, {wch: 30}, {wch: 15},
                        {wch: 10}, {wch: 5}, {wch: 15}, {wch: 15}, {wch: 15}, {wch: 35}
                    ];
                    
                    var hasProductFilter = $('#variation_id').val() !== '';
                    
                    if (!hasProductFilter) {
                        // Merge columns: Date(0), Name(1), Contact(2), Order(3), Invoice(4), Delivery(9), Shipping(10), Payment(11), Remark(12)
                        var mergeColumns = [0, 1, 2, 3, 4, 9, 10, 11, 12]; 
                        var merges = [];
                        var currentTransaction = null;
                        var transactionStartRow = null;
                        var transactionRowCount = 0;
                        
                        for (var rowIndex = 1; rowIndex < worksheetData.length; rowIndex++) {
                            var rowData = data[rowIndex - 1];
                            var transactionId = rowData.transaction_id;
                            
                            if (currentTransaction !== transactionId) {
                                if (currentTransaction !== null && transactionRowCount > 1) {
                                    mergeColumns.forEach(function(colIndex) {
                                        if (worksheetData[transactionStartRow][colIndex] && worksheetData[transactionStartRow][colIndex] !== '') {
                                            merges.push({ s: { r: transactionStartRow, c: colIndex }, e: { r: transactionStartRow + transactionRowCount - 1, c: colIndex } });
                                        }
                                    });
                                }
                                currentTransaction = transactionId; transactionStartRow = rowIndex; transactionRowCount = 1;
                            } else {
                                transactionRowCount++;
                            }
                        }
                        
                        if (currentTransaction !== null && transactionRowCount > 1) {
                            mergeColumns.forEach(function(colIndex) {
                                if (worksheetData[transactionStartRow][colIndex] && worksheetData[transactionStartRow][colIndex] !== '') {
                                    merges.push({ s: { r: transactionStartRow, c: colIndex }, e: { r: transactionStartRow + transactionRowCount - 1, c: colIndex } });
                                }
                            });
                        }
                        
                        ws['!merges'] = merges;
                    }
                    
                    XLSX.utils.book_append_sheet(wb, ws, 'Sales Detail Report');
                    XLSX.writeFile(wb, 'sales_detail_report_sellers.xlsx');
                    
                    toastr.success('Excel export completed successfully');
                });
            });
            
        }, 500);
    }

    var dateRangeSettings = {
        startDate: moment(),
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

    $('#sell_list_filter_date_range').daterangepicker(dateRangeSettings, function (start, end) {
        var format = moment_date_format || 'YYYY-MM-DD';
        if (!validateDateRange(start, end)) {
            showDateRangeError(true);
            var defaultStart = moment();
            var defaultEnd = moment();
            $('#sell_list_filter_date_range').data('daterangepicker').setStartDate(defaultStart);
            $('#sell_list_filter_date_range').data('daterangepicker').setEndDate(defaultEnd);
            $('#sell_list_filter_date_range').val(defaultStart.format(format) + ' ~ ' + defaultEnd.format(format));
            toastr.error('Date range cannot exceed 3 months (90 days). Please select a shorter period.');
            return false;
        } else {
            showDateRangeError(false);
            $('#sell_list_filter_date_range').val(start.format(format) + ' ~ ' + end.format(format));
        }
    });

    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        if (!validateDateRange(picker.startDate, picker.endDate)) {
            ev.preventDefault();
            showDateRangeError(true);
            toastr.error('Date range cannot exceed 3 months (90 days). Please select a shorter period.');
            return false;
        }
        showDateRangeError(false);
    });

    if ($('#search_product').length > 0) {
        $('#search_product').autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: '/purchases/get_products?check_enable_stock=false',
                    dataType: 'json',
                    data: {
                        term: request.term,
                    },
                    success: function(data) {
                        response(
                            $.map(data, function(v, i) {
                                if (v.variation_id) {
                                    return {
                                        label: v.text,
                                        value: v.variation_id
                                    };
                                }
                                return false;
                            })
                        );
                    },
                });
            },
            minLength: 2,
            select: function(event, ui) {
                $('#variation_id').val(ui.item.value).change();
                event.preventDefault();
                $(this).val(ui.item.label);
            },
            focus: function(event, ui) {
                event.preventDefault();
                $(this).val(ui.item.label);
            },
        });
    }

    $('#search_product').on('change', function() {
        if ($(this).val() === '') {
            $('#variation_id').val('');
        }
    });

    function initializeDataTable() {
        if (salesDetailReport) {
            salesDetailReport.destroy();
        }

        var columns = [
            { data: 'transaction_date', name: 'transaction_date' }, 
            { data: 'customer_name', name: 'customer_name' }, 
            { data: 'contact_id', name: 'contact_id' }, 
            { data: 'order_no', name: 'order_no' }, 
            { data: 'invoice_no', name: 'invoice_no' }, 
            { data: 'product_name', name: 'product_name' }, 
            { data: 'sku', name: 'sku' }, 
            { data: 'quantity', name: 'quantity', render: function(d) { return parseFloat(d).toFixed(2); } },
            { data: 'unit', name: 'unit', orderable: false },
            { data: 'delivery_person_name', name: 'delivery_person_name' }, // Added Column
            { data: 'shipping_status', name: 'shipping_status' }, 
            { data: 'payment_status', name: 'payment_status' }, 
            { data: 'remark', name: 'remark', orderable: false, render: function(d) { return (d && d !== '-') ? d.replace(/\n/g, '<br>') : (d || '-'); } }
        ];

        var columnDefs = [
            { "targets": [0, 1, 2, 3, 4, 5, 6, 8, 9], "className": "text-left" },
            { "targets": [7], "className": "text-right" },
            { "targets": [10, 11, 12], "className": "text-center" }, 
            { "width": "11%", "targets": 0 },
            { "width": "10%", "targets": 1 },
            { "width": "6%", "targets": 2 },
            { "width": "6%", "targets": 3 },
            { "width": "20%", "targets": 4 },
            { "width": "10%", "targets": 5 },
            { "width": "5%", "targets": 6 },
            { "width": "5%", "targets": 7 },
            { "width": "10%", "targets": 8 }, // Delivery Person
            { "width": "7%", "targets": 9 },
            { "width": "11%", "targets": 10 },
            { "width": "7%", "targets": 11 }
        ];

        salesDetailReport = $('#sell_detail_report_table').DataTable({
            processing: true,
            serverSide: true,
            scrollX: false,
            ajax: {
                url: "{{action([\App\Http\Controllers\ReportController::class, 'SaleDetailReportForSell'])}}",
                type: 'GET',
                data: function(d) {
                    d.sell_list_filter_location_id = $('#sell_list_filter_location_id').val();
                    d.sell_list_filter_date_range = $('#sell_list_filter_date_range').val();
                    d.variation_id = $('#variation_id').val();
                    d.customer_id = $('#customer_id').val();
                    d.customer_group_id = $('#customer_group_id').val();
                    d.category_id = $('#filter_category_id').val();
                    d.brand_id = $('#filter_brand_id').val();
                    d.ajax = 1;
                    d.apply_filters = (filters_applied && d.sell_list_filter_date_range) ? '1' : '0';
                },
                dataSrc: function(json) {
                    var totalQty = 0;
                    $.each(json.data, function(index, row) {
                        totalQty += parseFloat(row.quantity) || 0;
                    });
                    updateFooterTotals({
                        totalQuantity: totalQty.toFixed(2)
                    });
                    return json.data;
                },
                error: function(xhr, error, thrown) {
                    if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                        toastr.error(xhr.responseJSON.error);
                    } else if (xhr.status !== 200) {
                        toastr.error('An error occurred while loading the report.');
                    }
                    $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                    updateFooterTotals(null);
                }
            },
            columnDefs: columnDefs,
            columns: columns,
            language: {
                emptyTable: "Please select location and date range, then click Apply Filters to load data",
                zeroRecords: "No matching records found for the selected filters.",
                processing: "Loading sales detail data..."
            },
            order: [
                [0, 'desc']
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            initComplete: function() {
                this.api().columns.adjust();
                setupCustomExportButtons();
            },
            drawCallback: function() {
                __currency_convert_recursively($('#sell_detail_report_table'));
                var api = this.api(),
                    rows = api.rows({
                        page: 'current'
                    }).nodes(),
                    data = api.rows({
                        page: 'current'
                    }).data();
                var hasProductFilter = $('#variation_id').val() !== '';
                var processedTransactions = [],
                    transactionIndex = 0;
                data.each(function(rowData, index) {
                    if (rowData.is_first_row && !hasProductFilter && processedTransactions.indexOf(rowData.transaction_id) === -1) {
                        var productCount = rowData.product_count;
                        processedTransactions.push(rowData.transaction_id);
                        var groupClass = (transactionIndex % 2 === 0) ? 'transaction-group-even' : 'transaction-group-odd';
                        
                        // Updated shared columns: 0,1,2,3,4, 9,10,11,12 (Unit=8)
                        var sharedColumns = [0, 1, 2, 3, 4, 9, 10, 11, 12];
                        
                        sharedColumns.forEach(function(colIndex) {
                            var cell = $(rows[index]).find('td').eq(colIndex);
                            if (cell.length > 0 && productCount > 1) {
                                cell.attr('rowspan', productCount).addClass('merged-cell').css({
                                    'vertical-align': 'middle'
                                });
                                for (var i = 1; i < productCount; i++) {
                                    if (index + i < rows.length) $(rows[index + i]).find('td').eq(colIndex).hide();
                                }
                            }
                        });
                        for (var i = 0; i < productCount; i++) {
                            if (index + i < rows.length) {
                                $(rows[index + i]).removeClass('transaction-group-even transaction-group-odd').addClass(groupClass);
                            }
                        }
                        transactionIndex++;
                    }
                });
            },
            deferLoading: true
        });
    }

    initializeDataTable();

    $('#sell_list_filter_location_id, #customer_id, #customer_group_id, #filter_category_id, #filter_brand_id').on('change', function() {
        clearErrorStates();
        filters_applied = false;
        updateFooterTotals(null);
    });
    $('#search_product').on('change', function() {
        clearErrorStates();
        filters_applied = false;
        updateFooterTotals(null);
    });
    $('#sell_list_filter_date_range').on('show.daterangepicker', function() {
        clearErrorStates();
    });
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        if (!validateDateRange(picker.startDate, picker.endDate)) {
            ev.preventDefault();
            showDateRangeError(true);
            toastr.error('Date range cannot exceed 3 months (90 days). Please select a shorter period.');
            return false;
        }
        showDateRangeError(false);
        filters_applied = false;
        updateFooterTotals(null);
    });

    $('#apply_filters_btn').click(function() {
        var date_range = $('#sell_list_filter_date_range').val();
        if (!date_range || date_range === '') {
            showDateRangeError(true);
            toastr.error('{{ __("Please select a date range") }}');
            return;
        }
        if (date_range) {
            var dates = date_range.split(' ~ '),
                format = moment_date_format || 'YYYY-MM-DD';
            if (dates.length === 2) {
                var startDate = moment(dates[0], format),
                    endDate = moment(dates[1], format);
                if (!validateDateRange(startDate, endDate)) {
                    toastr.error('Date range cannot exceed 3 months (90 days). Please select a shorter period.');
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

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Loading...") }}');
        filters_applied = true;

        if (salesDetailReport) {
            salesDetailReport.ajax.reload(function(json) {
                $('#apply_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                var info = salesDetailReport.page.info();
                if (info.recordsTotal > 0) {
                    toastr.success('Report loaded successfully. Found ' + info.recordsTotal + ' records.');
                } else {
                    toastr.info('No records found for the selected criteria.');
                }
            }, false);
        }
    });
});
</script>
@endsection