@extends('layouts.app')

@section('title', __('Customer Reward Exchange'))

@section('content')

<style>
    .status-pending {
        background-color: orange;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-completed {
        background-color: #20c997;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .merged-cell {
        vertical-align: middle !important;
        font-weight: 500;
        background-color: #fff !important; 
        border-bottom: 1px solid #ddd;
    }
    
    /* --- HEADER LAYOUT FIXES --- */
    div.dt-buttons {
        float: none !important;
        display: inline-block !important;
        text-align: center !important;
        margin-bottom: 5px;
    }
    
    .dataTables_wrapper .row .text-center {
        text-align: center !important;
    }

    div.dataTables_wrapper div.dataTables_filter {
        text-align: right;
    }
    
    div.dataTables_wrapper div.dataTables_length {
        text-align: left;
    }

    .dt-button {
        background-color: #f4f4f4;
        color: #444;
        border: 1px solid #ddd;
        padding: 5px 10px;
        margin: 0 2px;
        border-radius: 3px;
    }
    
    /* Ensure no wrapping in headers */
    #sale_order_reward th {
        white-space: nowrap;
    }
</style>

<section class="content-header">
    <h1>{{ __('Customer Reward Exchange') }}</h1>
</section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_contact_id', __('Customer') . ':') !!}
                {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_status', __('Status') . ':') !!}
                {!! Form::select('sell_list_filter_status', ['' => __('All'), 'pending' => __('Pending'), 'completed' => __('Completed')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
    </div>
    @endcomponent
    
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                @slot('tool')
                    <div class="box-tools">
                        <button class="btn btn-block btn-primary" id="openAddRewardModal" onclick="window.location='{{ route('sales_reward.create') }}'">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </button>
                    </div>
                @endslot
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="sale_order_reward" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>@lang('messages.action')</th>
                                <th>@lang('Date')</th>
                                <th>@lang('Sales Order No')</th> 
                                <th>@lang('Invoice No')</th>
                                <th>@lang('Contact Name')</th>
                                <th>@lang('Contact Mobile')</th>
                                <th>@lang('Location')</th>
                                
                                <th>@lang('Exchange Product')</th>
                                <th>@lang('Receive Product')</th>
                                
                                <th>@lang('Set Quantity')</th>
                                <th>@lang('Used Ring Balance')</th>
                                
                                <th>@lang('Status')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.0/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.0/vfs_fonts.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
    var rewardTable;
    var startDate = moment().startOf('month').format('YYYY-MM-DD'); 
    var endDate = moment().endOf('month').format('YYYY-MM-DD');

    // Initialize Date Range Picker
    $('#sell_list_filter_date_range').daterangepicker({
        locale: { format: 'YYYY-MM-DD' },
        startDate: startDate,
        endDate: endDate,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'This Year': [moment().startOf('year'), moment().endOf('year')],
        }
    });
    $('#sell_list_filter_date_range').val(startDate + ' ~ ' + endDate);

    // --- MAIN DATATABLE INITIALIZATION ---
    
function initializeDataTable() {
    if (rewardTable) {
        rewardTable.destroy();
    }
    
    rewardTable = $('#sale_order_reward').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        
        dom: '<"row"<"col-md-3"l><"col-md-6 text-center"B><"col-md-3"f>>rtip',
        
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fa fa-file-text-o"></i> Export to CSV',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible:not(:first-child)' },
                action: function (e, dt, node, config) { exportToCSV(); }
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel-o"></i> Export to Excel',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible:not(:first-child)' },
                action: function (e, dt, node, config) { exportToExcel(); }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> Print',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible:not(:first-child)' }
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-columns"></i> Column visibility',
                className: 'btn btn-default btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fa fa-file-pdf-o"></i> Export to PDF',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible:not(:first-child)' },
                action: function (e, dt, node, config) { exportToPDF(); }
            }
        ],
        ajax: {
            url: '{{ url("sales-reward") }}',
            data: function (d) {
                d.location_id = $('#sell_list_filter_location_id').val();
                d.contact_id = $('#sell_list_filter_contact_id').val();
                d.status = $('#sell_list_filter_status').val();
                if ($('#sell_list_filter_date_range').data('daterangepicker')) {
                    d.start_date = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    d.end_date = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                }
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false, width: '4%'},
            {data: 'date', name: 'date', width: '9%'},
            {data: 'sales_order_no', name: 'sales_order_no', width: '7%'}, // NEW COLUMN
            {data: 'reward_no', name: 'reward_no', width: '7%'},
            {data: 'contact_name', name: 'contact_name', width: '9%'},
            {data: 'contact_mobile', name: 'contact_mobile', width: '7%'},
            {data: 'location_name', name: 'location_name', width: '7%'},
            {data: 'exchange_product', name: 'exchange_product', width: '14%'},
            {data: 'receive_product', name: 'receive_product', width: '14%'},
            {data: 'set_quantity', name: 'set_quantity', width: '8%', className: 'text-right'},
            {data: 'used_ring_balance', name: 'used_ring_balance', width: '10%', className: 'text-right'},
            {
                data: 'status', 
                name: 'status',
                width: '5%',
                render: function(data, type, row) {
                    if (data === 'pending') return '<span class="status-pending">Pending</span>';
                    if (data === 'completed') return '<span class="status-completed">Completed</span>';
                    return data;
                }
            }
        ],
        columnDefs: [
            { "className": "text-left", "targets": [1, 2, 3, 4, 5, 6, 7, 8] },
            { "className": "text-center", "targets": [0, 11] }
        ],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        drawCallback: function(settings) {
            var api = this.api(),
                rows = api.rows({page:'current'}).nodes(),
                data = api.rows({page:'current'}).data();

            var processedTransactions = [],
                transactionIndex = 0;

            data.each(function(rowData, index) {
                var transactionId = rowData.transaction_id;

                if (rowData.is_first_row && processedTransactions.indexOf(transactionId) === -1) {
                    processedTransactions.push(transactionId);
                    var productCount = rowData.product_count;
                    
                    // Merge transaction-level columns: 
                    // Action(0), Date(1), Sales Order(2), Invoice(3), Contact(4), Mobile(5), Location(6), Status(11)
                    var mergeColumns = [0, 1, 2, 3, 4, 5, 6, 11];
                    var groupClass = (transactionIndex % 2 === 0) ? 'transaction-group-even' : 'transaction-group-odd';

                    $(rows[index]).removeClass('transaction-group-even transaction-group-odd')
                        .addClass(groupClass)
                        .addClass('transaction-group-row transaction-group-start');

                    if (productCount > 1) {
                        mergeColumns.forEach(function(colIndex) {
                            var cell = $(rows[index]).find('td').eq(colIndex);
                            if (cell.length > 0) {
                                cell.attr('rowspan', productCount)
                                    .addClass('merged-cell')
                                    .css({
                                        'vertical-align': 'middle',
                                        'font-weight': '500'
                                    });

                                for (var i = 1; i < productCount; i++) {
                                    if (index + i < rows.length) {
                                        $(rows[index + i]).find('td').eq(colIndex).hide();
                                    }
                                }
                            }
                        });
                    }

                    for (var i = 0; i < productCount; i++) {
                        if (index + i < rows.length) {
                            var row = $(rows[index + i])
                                .removeClass('transaction-group-even transaction-group-odd')
                                .addClass(groupClass)
                                .addClass('transaction-group-row');
                            
                            if (i === 0) {
                                row.addClass('transaction-group-start');
                            }
                        }
                    }

                    transactionIndex++;
                }
            });
        }
    });
}

    initializeDataTable();

    // Reload Filters
    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_status').change(function() {
        rewardTable.ajax.reload(null, false);
    });
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        rewardTable.ajax.reload();
    });

    // --- EXPORT FUNCTIONS (FETCH ALL DATA) ---
    function getAllDataForExport(callback) {
        var params = {
            sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
            sell_list_filter_contact_id: $('#sell_list_filter_contact_id').val(),
            sell_list_filter_status: $('#sell_list_filter_status').val(),
            length: -1,
            start: 0,
            ajax: 1
        };
        if ($('#sell_list_filter_date_range').data('daterangepicker')) {
            params.start_date = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
            params.end_date = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
        }

        $.ajax({
            url: "{{ url('sales-reward') }}",
            type: 'GET',
            data: params,
            success: function(response) {
                callback(response.data || []);
            },
            error: function() {
                toastr.error('Failed to fetch export data');
                callback([]);
            }
        });
    }

    // --- UPDATED EXPORT DATA PROCESSOR (REORDERED COLUMNS) ---
    function processDataForExport(data) {
    var processedData = [];
    var lastTransactionId = null;

    data.forEach(function(row) {
        var isFirstRowOfTransaction = (row.transaction_id !== lastTransactionId);
        
        var date = isFirstRowOfTransaction ? (row.date || '') : '';
        var salesOrder = isFirstRowOfTransaction ? (row.sales_order_invoice_no || '') : '';
        var invoice = isFirstRowOfTransaction ? (row.reward_no || '') : '';
        var customer = isFirstRowOfTransaction ? (row.contact_name || '') : '';
        var mobile = isFirstRowOfTransaction ? (row.contact_mobile || '') : '';
        var location = isFirstRowOfTransaction ? (row.location_name || '') : '';
        var status = isFirstRowOfTransaction ? (row.status || '') : '';

        processedData.push([
            date,
            salesOrder,
            invoice,
            customer,
            mobile,
            location,
            row.exchange_product || '',
            row.receive_product || '',
            row.set_quantity || '',
            row.used_ring_balance || '',
            status
        ]);

        lastTransactionId = row.transaction_id;
    });
    return processedData;
}

    function exportToCSV() {
    getAllDataForExport(function(data) {
        if (data.length === 0) { toastr.warning('No data to export'); return; }
        
        var headers = ['Date', 'Sales Order', 'Invoice No', 'Contact Name', 'Contact Mobile', 'Location', 'Exchange Product', 'Receive Product', 'Set Quantity', 'Used Ring Balance', 'Status'];
        var csvContent = [headers.join(',')];
        
        var processed = processDataForExport(data);
        processed.forEach(function(row) {
            var escaped = row.map(val => '"' + String(val).replace(/"/g, '""') + '"');
            csvContent.push(escaped.join(','));
        });

        var blob = new Blob([csvContent.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'reward_exchange_report.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}

    function exportToExcel() {
        if (typeof XLSX === 'undefined') { toastr.error('Excel library not loaded'); return; }
        getAllDataForExport(function(data) {
            if (data.length === 0) { toastr.warning('No data to export'); return; }
            
            var processed = processDataForExport(data);
            var headers = ['Date', 'Sales Order', 'Invoice No', 'Contact Name', 'Contact Mobile', 'Location', 'Exchange Product', 'Receive Product', 'Set Quantity', 'Used Ring Balance', 'Status'];
            
            var wsData = [headers].concat(processed);
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(wsData);
            
            var wscols = [
                {wch: 15}, {wch: 12}, {wch: 12}, {wch: 20}, {wch: 12}, {wch: 15},
                {wch: 20}, {wch: 20},
                {wch: 12}, {wch: 18},
                {wch: 10}
            ];
            ws['!cols'] = wscols;

            // Excel Merge Logic - updated merge columns
            var merges = [];
            var mergeCols = [0, 1, 2, 3, 4, 5, 10]; // Date, Sales Order, Invoice, Contact, Mobile, Location, Status
            var txStart = 0;

            for (var i = 1; i <= data.length; i++) {
                if (i == data.length || data[i].transaction_id != data[txStart].transaction_id) {
                    var count = i - txStart;
                    if (count > 1) {
                        var s_r = txStart + 1;
                        var e_r = i;
                        mergeCols.forEach(function(c) {
                            merges.push({ s: {r: s_r, c: c}, e: {r: e_r, c: c} });
                        });
                    }
                    txStart = i;
                }
            }
            ws['!merges'] = merges;

            XLSX.utils.book_append_sheet(wb, ws, 'Reward Exchange');
            XLSX.writeFile(wb, 'reward_exchange_report.xlsx');
        });
    }

    function exportToPDF() {
        if (typeof pdfMake === 'undefined') { toastr.error('PDF library not loaded'); return; }
        getAllDataForExport(function(data) {
            if (data.length === 0) { toastr.warning('No data to export'); return; }
            var processed = processDataForExport(data);
            
            var headers = ['Date', 'Sales Order', 'Inv No', 'Contact', 'Mobile', 'Location', 'Exch Prod', 'Rec Prod', 'Set Qty', 'Used Ring', 'Status'];
            
            var docDefinition = {
                content: [
                    { text: 'Reward Exchange Report', style: 'header' },
                    {
                        table: {
                            headerRows: 1,
                            widths: ['8%', '8%', '7%', '10%', '8%', '8%', '13%', '13%', '7%', '10%', '8%'],
                            body: [headers].concat(processed)
                        },
                        layout: {
                            fillColor: function (rowIndex, node, columnIndex) {
                                return (rowIndex === 0) ? '#CCCCCC' : null;
                            }
                        }
                    }
                ],
                pageOrientation: 'landscape',
                styles: { 
                    header: { fontSize: 18, bold: true, margin: [0, 0, 0, 10] },
                    tableHeader: { bold: true, fontSize: 10, color: 'black' }
                },
                defaultStyle: { fontSize: 8 }
            };
            pdfMake.createPdf(docDefinition).download('reward_exchange_report.pdf');
        });
    }

    // Delete Logic
    $(document).on('click', '.delete-reward-exchange', function(e) {
        e.preventDefault();
        var url = $(this).data('href');
        var csrfToken = $(this).data('csrf');
        swal({
            title: "Are you sure ?",
            text: "This will delete the reward exchange transaction and reverse all stock changes.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: { "_token": csrfToken },
                    success: function(response) {
                        if (response.success) {
                            swal("Deleted!", response.message, "success");
                            rewardTable.ajax.reload();
                        } else {
                            swal("Failed!", response.message || 'Error occurred', "error");
                        }
                    },
                    error: function(xhr) {
                        swal("Error!", "Something went wrong", "error");
                    }
                });
            }
        });
    });
});
</script>
@endsection