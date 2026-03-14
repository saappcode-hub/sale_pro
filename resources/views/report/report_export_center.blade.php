@extends('layouts.app')
@section('title', __('Report Export Center'))

@section('content')

<section class="content-header">
    <h1>{{ __('Report Export Center')}}</h1>
</section>

<style>
    .apply-btn-container {
        display: flex;
        align-items: end;
        margin-top: 25px;
    }
    
    .export-section {
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 5px;
        margin-top: 20px;
        border: 1px solid #ddd;
        min-height: 200px;
    }
    
    .export-buttons {
        text-align: center;
        margin-top: 15px;
    }
    
    .export-buttons .btn {
        margin: 0 10px;
        min-width: 150px;
    }
    
    .filters-applied-message {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 10px;
        border-radius: 4px;
        margin-top: 15px;
        display: none;
    }
    
    /* Error highlighting for form fields */
    .has-error .form-control,
    .has-error .select2-container--default .select2-selection--single {
        border-color: #d9534f;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 6px #ce8483;
    }
    .has-error .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #d9534f;
    }
    
    /* Loading state for apply button */
    .btn:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }
    
    .section-divider {
        margin: 30px 0;
        border-top: 2px solid #ddd;
    }

    /* Styles for the manual filter box */
    .box-filters {
        border-top: 3px solid #3c8dbc;
        background: #fff;
        border-radius: 3px;
        margin-bottom: 20px;
        width: 100%;
        box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    }
    .box-filters .box-header {
        color: #444;
        display: block;
        padding: 10px;
        position: relative;
    }
    .box-filters .box-header .box-title {
        display: inline-block;
        font-size: 18px;
        margin: 0;
        line-height: 1;
    }
    .box-filters .box-body {
        border-top-left-radius: 0;
        border-top-right-radius: 0;
        border-bottom-right-radius: 3px;
        border-bottom-left-radius: 3px;
        padding: 10px;
    }
</style>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <h3><i class="fa fa-line-chart"></i> Sales Report by Zone</h3>
        </div>
    </div>
    
    <div class="box box-primary" id="sales_accordion">
        <div class="box-header with-border" style="cursor: pointer;">
            <h3 class="box-title">
                <a data-toggle="collapse" data-parent="#sales_accordion" href="#sales_filter_collapse">
                    <i class="fa fa-filter" aria-hidden="true"></i> {{ __('report.filters') }}
                </a>
            </h3>
        </div>
        <div id="sales_filter_collapse" class="panel-collapse active collapse in" aria-expanded="true">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('sales_location_id', __('purchase.business_location') . ':') !!}
                            {!! Form::select('sales_location_id', $business_locations, $location_id ?? '', ['class' => 'form-control select2', 'id' => 'sales_location_id', 'style' => 'width:100%', 'placeholder' => __('Please Select')]) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('sales_date_range', __('report.date_range') . ':') !!}
                            {!! Form::text('sales_date_range', $date_range ?? '', ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'sales_date_range', 'readonly']) !!}
                        </div>
                    </div>
                    <div class="col-md-4 apply-btn-container">
                        <button type="button" id="apply_sales_filters_btn" class="btn btn-primary">
                            <i class="fa fa-search"></i> {{ __('Apply Filters') }}
                        </button>
                    </div>
                </div>
                <div class="filters-applied-message" id="sales_filters_applied_message">
                    <i class="fa fa-check-circle"></i> <span id="sales_filter_message_text">Filters applied successfully. Data is ready for export.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="export-section">
                    <h4 class="text-center"><i class="fa fa-download"></i> Sales Report by Zone</h4>
                    <p class="text-center text-muted">Export sales data organized by customer zones and products</p>
                    
                    <div class="export-buttons">
                        <button type="button" id="export_sales_csv_btn" class="btn btn-success" disabled>
                            <i class="fa fa-file-text-o"></i> Export to CSV
                        </button>
                        <button type="button" id="export_sales_excel_btn" class="btn btn-info" disabled>
                            <i class="fa fa-file-excel-o"></i> Export to Excel
                        </button>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="section-divider"></div>

    <div class="row">
        <div class="col-md-12">
            <h3><i class="fa fa-shopping-cart"></i> Purchase Report</h3>
        </div>
    </div>
    
    <div class="box box-primary" id="purchase_accordion">
        <div class="box-header with-border" style="cursor: pointer;">
            <h3 class="box-title">
                <a data-toggle="collapse" data-parent="#purchase_accordion" href="#purchase_filter_collapse">
                    <i class="fa fa-filter" aria-hidden="true"></i> {{ __('report.filters') }}
                </a>
            </h3>
        </div>
        <div id="purchase_filter_collapse" class="panel-collapse active collapse in" aria-expanded="true">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('purchase_location_id', __('purchase.business_location') . ':') !!}
                            {!! Form::select('purchase_location_id', $business_locations, $location_id ?? '', ['class' => 'form-control select2', 'id' => 'purchase_location_id', 'style' => 'width:100%', 'placeholder' => __('Please Select')]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('supplier_id', __('purchase.supplier') . ':') !!}
                            {!! Form::select('supplier_id', $suppliers, null, ['class' => 'form-control select2', 'id' => 'supplier_id', 'style' => 'width:100%', 'placeholder' => __('All')]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('purchase_date_range', __('report.date_range') . ':') !!}
                            {!! Form::text('purchase_date_range', $date_range ?? '', ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'purchase_date_range', 'readonly']) !!}
                        </div>
                    </div>
                    <div class="col-md-3 apply-btn-container">
                        <button type="button" id="apply_purchase_filters_btn" class="btn btn-primary">
                            <i class="fa fa-search"></i> {{ __('Apply Filters') }}
                        </button>
                    </div>
                </div>
                <div class="filters-applied-message" id="purchase_filters_applied_message">
                    <i class="fa fa-check-circle"></i> <span id="purchase_filter_message_text">Filters applied successfully. Data is ready for export.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="export-section">
                    <h4 class="text-center"><i class="fa fa-download"></i> Purchase Report</h4>
                    <p class="text-center text-muted">Export purchase data organized by supplier, date, and purchase type</p>
                    
                    <div class="export-buttons">
                        <button type="button" id="export_purchase_csv_btn" class="btn btn-success" disabled>
                            <i class="fa fa-file-text-o"></i> Export to CSV
                        </button>
                        <button type="button" id="export_purchase_excel_btn" class="btn btn-info" disabled>
                            <i class="fa fa-file-excel-o"></i> Export to Excel
                        </button>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
    var sales_filters_applied = false;
    var purchase_filters_applied = false;

    // Function to clear error states for sales filters
    function clearSalesErrorStates() {
        $('#sales_location_id').removeClass('has-error');
        $('#sales_date_range').removeClass('has-error');
    }

    // Function to clear error states for purchase filters
    function clearPurchaseErrorStates() {
        $('#purchase_location_id').removeClass('has-error');
        $('#purchase_date_range').removeClass('has-error');
    }

    // Initialize date range picker settings
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

    // Initialize sales date range picker
    $('#sales_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            var format = moment_date_format || 'YYYY-MM-DD';
            $('#sales_date_range').val(start.format(format) + ' ~ ' + end.format(format));
            clearSalesErrorStates();
            sales_filters_applied = false;
            updateSalesExportButtons();
        }
    );

    // Initialize purchase date range picker
    $('#purchase_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            var format = moment_date_format || 'YYYY-MM-DD';
            $('#purchase_date_range').val(start.format(format) + ' ~ ' + end.format(format));
            clearPurchaseErrorStates();
            purchase_filters_applied = false;
            updatePurchaseExportButtons();
        }
    );

    // Sales filter change handlers
    $('#sales_location_id, #sales_date_range').on('change', function() {
        clearSalesErrorStates();
        sales_filters_applied = false;
        updateSalesExportButtons();
    });

    // Purchase filter change handlers
    $('#purchase_location_id, #supplier_id, #purchase_date_range').on('change', function() {
        clearPurchaseErrorStates();
        purchase_filters_applied = false;
        updatePurchaseExportButtons();
    });

    // Update sales export button states
    function updateSalesExportButtons() {
        if (sales_filters_applied) {
            $('#export_sales_csv_btn, #export_sales_excel_btn').prop('disabled', false);
            $('#sales_filters_applied_message').show();
        } else {
            $('#export_sales_csv_btn, #export_sales_excel_btn').prop('disabled', true);
            $('#sales_filters_applied_message').hide();
        }
    }

    // Update purchase export button states
    function updatePurchaseExportButtons() {
        if (purchase_filters_applied) {
            $('#export_purchase_csv_btn, #export_purchase_excel_btn').prop('disabled', false);
            $('#purchase_filters_applied_message').show();
        } else {
            $('#export_purchase_csv_btn, #export_purchase_excel_btn').prop('disabled', true);
            $('#purchase_filters_applied_message').hide();
        }
    }

    // Apply Sales Filters
    $('#apply_sales_filters_btn').click(function() {
        var location_id = $('#sales_location_id').val();
        var date_range = $('#sales_date_range').val();
        var hasErrors = false;

        clearSalesErrorStates();

        if (!location_id || location_id === '') {
            $('#sales_location_id').addClass('has-error');
            toastr.error('{{ __("Please select a business location") }}');
            hasErrors = true;
        }
        
        if (!date_range || date_range === '') {
            $('#sales_date_range').addClass('has-error');
            toastr.error('{{ __("Please select a date range") }}');
            hasErrors = true;
        }

        if (hasErrors) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Processing...") }}');

        $.ajax({
            url: "{{ route('reports.report-export-center') }}",
            type: 'GET',
            data: {
                sell_list_filter_location_id: location_id,
                sell_list_filter_date_range: date_range,
                report_type: 'sales',
                ajax: 1,
                apply_filters: '1'
            },
            success: function(response) {
                $('#apply_sales_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                
                if (response.success) {
                    sales_filters_applied = true;
                    updateSalesExportButtons();
                    $('#sales_filter_message_text').text(response.message);
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                $('#apply_sales_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                console.log('AJAX Error:', xhr);
                toastr.error('An error occurred while processing the filters.');
            }
        });
    });

    // Apply Purchase Filters
    $('#apply_purchase_filters_btn').click(function() {
        var location_id = $('#purchase_location_id').val();
        var date_range = $('#purchase_date_range').val();
        var supplier_id = $('#supplier_id').val();
        var hasErrors = false;

        clearPurchaseErrorStates();

        if (!location_id || location_id === '') {
            $('#purchase_location_id').addClass('has-error');
            toastr.error('{{ __("Please select a business location") }}');
            hasErrors = true;
        }
        
        if (!date_range || date_range === '') {
            $('#purchase_date_range').addClass('has-error');
            toastr.error('{{ __("Please select a date range") }}');
            hasErrors = true;
        }

        if (hasErrors) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Processing...") }}');

        $.ajax({
            url: "{{ route('reports.report-export-center') }}",
            type: 'GET',
            data: {
                sell_list_filter_location_id: location_id,
                sell_list_filter_date_range: date_range,
                supplier_id: supplier_id,
                report_type: 'purchase',
                ajax: 1,
                apply_filters: '1'
            },
            success: function(response) {
                $('#apply_purchase_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                
                if (response.success) {
                    purchase_filters_applied = true;
                    updatePurchaseExportButtons();
                    $('#purchase_filter_message_text').text(response.message);
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                $('#apply_purchase_filters_btn').prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
                console.log('AJAX Error:', xhr);
                toastr.error('An error occurred while processing the filters.');
            }
        });
    });

    // Sales Export Handlers
    $('#export_sales_csv_btn').click(function() {
        if (!sales_filters_applied) {
            toastr.error('Please apply filters first.');
            return;
        }
        exportSalesData('csv');
    });

    $('#export_sales_excel_btn').click(function() {
        if (!sales_filters_applied) {
            toastr.error('Please apply filters first.');
            return;
        }
        exportSalesData('excel');
    });

    // Purchase Export Handlers
    $('#export_purchase_csv_btn').click(function() {
        if (!purchase_filters_applied) {
            toastr.error('Please apply filters first.');
            return;
        }
        exportPurchaseData('csv');
    });

    $('#export_purchase_excel_btn').click(function() {
        if (!purchase_filters_applied) {
            toastr.error('Please apply filters first.');
            return;
        }
        exportPurchaseData('excel');
    });

    // Sales Export Function
    function exportSalesData(exportType) {
        var location_id = $('#sales_location_id').val();
        var date_range = $('#sales_date_range').val();
        
        if (!location_id || !date_range) {
            toastr.error('Please select location and date range first.');
            return;
        }

        var button = exportType === 'csv' ? $('#export_sales_csv_btn') : $('#export_sales_excel_btn');
        var originalText = button.html();
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');

        $.ajax({
            url: "{{ route('reports.report-export-center') }}",
            type: 'GET',
            data: {
                sell_list_filter_location_id: location_id,
                sell_list_filter_date_range: date_range,
                report_type: 'sales',
                ajax: 1,
                get_export_data: '1'
            },
            success: function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success && response.data) {
                    if (exportType === 'csv') {
                        exportToCSV(response.data, response.filename || 'sales_by_zone_export');
                    } else {
                        exportSalesToExcel(response.data, response.filename || 'sales_by_zone_export');
                    }
                    toastr.success('Export completed successfully');
                } else {
                    toastr.error(response.message || 'No data found for export');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false).html(originalText);
                console.log('AJAX Error:', xhr);
                toastr.error('An error occurred while exporting data.');
            }
        });
    }

    // Purchase Export Function
    function exportPurchaseData(exportType) {
        var location_id = $('#purchase_location_id').val();
        var date_range = $('#purchase_date_range').val();
        var supplier_id = $('#supplier_id').val();
        
        if (!location_id || !date_range) {
            toastr.error('Please select location and date range first.');
            return;
        }

        var button = exportType === 'csv' ? $('#export_purchase_csv_btn') : $('#export_purchase_excel_btn');
        var originalText = button.html();
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');

        $.ajax({
            url: "{{ route('reports.report-export-center') }}",
            type: 'GET',
            data: {
                sell_list_filter_location_id: location_id,
                sell_list_filter_date_range: date_range,
                supplier_id: supplier_id,
                report_type: 'purchase',
                ajax: 1,
                get_export_data: '1'
            },
            success: function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success && response.data) {
                    if (exportType === 'csv') {
                        exportToCSV(response.data, response.filename || 'purchase_report_export');
                    } else {
                        exportPurchaseToExcel(response.data, response.filename || 'purchase_report_export');
                    }
                    toastr.success('Export completed successfully');
                } else {
                    toastr.error(response.message || 'No data found for export');
                }
            },
            error: function(xhr) {
                button.prop('disabled', false).html(originalText);
                console.log('AJAX Error:', xhr);
                toastr.error('An error occurred while exporting data.');
            }
        });
    }

    // CSV Export Function (works for both sales and purchase)
    function exportToCSV(data, filename) {
        if (!data || data.length === 0) {
            toastr.warning('No data to export');
            return;
        }
        
        var headers = Object.keys(data[0]);
        var csvContent = [];
        csvContent.push(headers.join(','));
        
        data.forEach(function(row) {
            var values = headers.map(function(header) {
                var value = row[header] || '';
                var stringValue = String(value);
                if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
                    stringValue = '"' + stringValue.replace(/"/g, '""') + '"';
                }
                return stringValue;
            });
            csvContent.push(values.join(','));
        });
        
        var blob = new Blob(['\uFEFF' + csvContent.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Sales Excel Export Function
    function exportSalesToExcel(data, filename) {
        if (typeof XLSX === 'undefined') {
            toastr.error('Excel export library not loaded');
            return;
        }
        
        if (!data || data.length === 0) {
            toastr.warning('No data to export');
            return;
        }
        
        var headers = Object.keys(data[0]);
        var worksheetData = [headers];
        data.forEach(function(row) {
            var rowData = headers.map(function(header) {
                return row[header] || '';
            });
            worksheetData.push(rowData);
        });
        
        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.aoa_to_sheet(worksheetData);
        
        // Set column widths
        var maxWidths = headers.map(function() { return 15; });
        ws['!cols'] = maxWidths.map(function(width) {
            return { wch: width };
        });
        
        XLSX.utils.book_append_sheet(wb, ws, 'Sales by Zone');
        XLSX.writeFile(wb, filename + '.xlsx');
    }

   function exportPurchaseToExcel(data, filename) {
    if (typeof XLSX === 'undefined') {
        toastr.error('Excel export library not loaded');
        return;
    }
    
    if (!data || data.length === 0) {
        toastr.warning('No data to export');
        return;
    }
    
    // Create workbook
    var wb = XLSX.utils.book_new();
    
    // Get all unique purchase types from the data
    var purchaseTypes = new Set();
    data.forEach(function(row) {
        Object.keys(row).forEach(function(key) {
            if (key.startsWith('p_') && key.includes('_t_')) {
                var typeId = key.split('_t_')[1];
                purchaseTypes.add(typeId);
            }
        });
    });
    var purchaseTypesArray = Array.from(purchaseTypes);
    
    // Get all unique products
    var products = [];
    var productMap = {};
    data.forEach(function(row) {
        Object.keys(row).forEach(function(key) {
            if (key.endsWith('_header')) {
                var productId = key.replace('_header', '');
                if (!productMap[productId]) {
                    productMap[productId] = row[key];
                    products.push({
                        id: productId,
                        name: row[key]
                    });
                }
            }
        });
    });
    
    // Build worksheet data
    var worksheetData = [];
    var merges = [];
    var currentRow = 0;
    
    // Process each row
    data.forEach(function(row, index) {
        // Skip completely empty rows (blank Date and all values are empty or '0')
        if (row.Date === '' && index > 0) {
            var hasData = false;
            Object.keys(row).forEach(function(key) {
                if (key !== 'Date' && row[key] && row[key] !== '0') {
                    hasData = true;
                }
            });
            if (!hasData) {
                return; // Skip this row
            }
        }
        
        var isSupplierRow = row.Date && row.Date.startsWith('SUPPLIER:');
        var isProductHeaderRow = row.Date === 'Date' || (row.Date === '' && index > 0 && data[index-1].Date.startsWith('SUPPLIER:'));
        var isTypeHeaderRow = row.Date === '' && index > 1 && data[index-2].Date.startsWith('SUPPLIER:');
        var isTotalRow = row.Date === 'Total';
        
        if (isSupplierRow) {
            // Supplier header row - merge across all columns
            var supplierRow = [row.Date];
            var colCount = 1 + (products.length * purchaseTypesArray.length);
            for (var i = 1; i < colCount; i++) {
                supplierRow.push('');
            }
            worksheetData.push(supplierRow);
            
            // Add merge for supplier header
            merges.push({
                s: { r: currentRow, c: 0 },
                e: { r: currentRow, c: colCount - 1 }
            });
            currentRow++;
            
        } else if (isProductHeaderRow) {
            // Product header row (first level)
            var headerRow = ['Date'];
            products.forEach(function(product) {
                headerRow.push(product.name);
                // Add empty cells for remaining purchase types
                for (var i = 1; i < purchaseTypesArray.length; i++) {
                    headerRow.push('');
                }
            });
            worksheetData.push(headerRow);
            
            // Add merges for each product header
            var colIndex = 1;
            products.forEach(function(product) {
                merges.push({
                    s: { r: currentRow, c: colIndex },
                    e: { r: currentRow, c: colIndex + purchaseTypesArray.length - 1 }
                });
                colIndex += purchaseTypesArray.length;
            });
            currentRow++;
            
        } else if (isTypeHeaderRow) {
            // Purchase type header row (second level)
            var typeRow = [''];
            products.forEach(function(product) {
                purchaseTypesArray.forEach(function(typeId) {
                    var typeKey = 'p_' + product.id + '_t_' + typeId;
                    typeRow.push(row[typeKey] || '');
                });
            });
            worksheetData.push(typeRow);
            currentRow++;
            
        } else {
            // Data or Total row
            var dataRow = [row.Date || ''];
            products.forEach(function(product) {
                purchaseTypesArray.forEach(function(typeId) {
                    var key = 'p_' + product.id + '_t_' + typeId;
                    dataRow.push(row[key] || '0');
                });
            });
            worksheetData.push(dataRow);
            currentRow++;
        }
    });
    
    // Create worksheet
    var ws = XLSX.utils.aoa_to_sheet(worksheetData);
    
    // Apply merges
    ws['!merges'] = merges;
    
    // Set column widths
    ws['!cols'] = [];
    ws['!cols'][0] = { wch: 18 }; // Date column
    for (var i = 0; i < products.length * purchaseTypesArray.length; i++) {
        ws['!cols'].push({ wch: 12 });
    }
    
    // Apply styling
    var range = XLSX.utils.decode_range(ws['!ref']);
    
    // Track which rows are what type for styling
    var rowTypes = [];
    var wsRowIndex = 0;
    data.forEach(function(row, index) {
        var isSupplierRow = row.Date && row.Date.startsWith('SUPPLIER:');
        var isProductHeaderRow = row.Date === 'Date' || (row.Date === '' && index > 0 && data[index-1].Date.startsWith('SUPPLIER:'));
        var isTypeHeaderRow = row.Date === '' && index > 1 && data[index-2].Date.startsWith('SUPPLIER:');
        var isTotalRow = row.Date === 'Total';
        
        if (isSupplierRow) {
            rowTypes.push('supplier');
            wsRowIndex++;
        } else if (isProductHeaderRow) {
            rowTypes.push('product_header');
            wsRowIndex++;
        } else if (isTypeHeaderRow) {
            rowTypes.push('type_header');
            wsRowIndex++;
        } else if (isTotalRow) {
            rowTypes.push('total');
            wsRowIndex++;
        } else {
            rowTypes.push('data');
            wsRowIndex++;
        }
    });
    
    // Apply styles based on row type
    for (var R = range.s.r; R <= range.e.r; ++R) {
        var rowType = rowTypes[R];
        
        for (var C = range.s.c; C <= range.e.c; ++C) {
            var cellAddr = XLSX.utils.encode_cell({r: R, c: C});
            if (!ws[cellAddr]) ws[cellAddr] = { v: '' };
            
            if (rowType === 'supplier') {
                ws[cellAddr].s = {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "366092" } },
                    alignment: { horizontal: "center", vertical: "center" }
                };
            } else if (rowType === 'product_header') {
                ws[cellAddr].s = {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "4472C4" } },
                    alignment: { horizontal: "center", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "000000" } },
                        bottom: { style: "thin", color: { rgb: "000000" } },
                        left: { style: "thin", color: { rgb: "000000" } },
                        right: { style: "thin", color: { rgb: "000000" } }
                    }
                };
            } else if (rowType === 'type_header') {
                ws[cellAddr].s = {
                    font: { bold: true, color: { rgb: "000000" } },
                    fill: { fgColor: { rgb: "B4C7E7" } },
                    alignment: { horizontal: "center", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "000000" } },
                        bottom: { style: "thin", color: { rgb: "000000" } },
                        left: { style: "thin", color: { rgb: "000000" } },
                        right: { style: "thin", color: { rgb: "000000" } }
                    }
                };
            } else if (rowType === 'total') {
                ws[cellAddr].s = {
                    font: { bold: true },
                    fill: { fgColor: { rgb: "D3D3D3" } },
                    alignment: { horizontal: "center" }
                };
            } else {
                // Data rows - add borders
                ws[cellAddr].s = {
                    border: {
                        top: { style: "thin", color: { rgb: "D3D3D3" } },
                        bottom: { style: "thin", color: { rgb: "D3D3D3" } },
                        left: { style: "thin", color: { rgb: "D3D3D3" } },
                        right: { style: "thin", color: { rgb: "D3D3D3" } }
                    }
                };
            }
        }
    }
    
    // Freeze panes (freeze first 3 rows and first column)
    ws['!freeze'] = { xSplit: 1, ySplit: 3 };
    
    // Add sheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Purchase Report');
    
    // Write file
    XLSX.writeFile(wb, filename + '.xlsx');
}

    // Initialize on page load
    updateSalesExportButtons();
    updatePurchaseExportButtons();
});
</script>
@endsection