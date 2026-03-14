@extends('layouts.app')

@section('title', __('Customer Reward Exchange'))

@section('content')

<style>
    .status-pending { background-color: orange; color: white; padding: 5px 10px; border-radius: 5px; text-align: center; display: block; width: 100%;}
    .status-completed { background-color: #20c997; color: white; padding: 5px 10px; border-radius: 5px; text-align: center; display: block; width: 100%;}
    /* New Style for Completed ATU */
    .status-completed-atu { background-color: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; text-align: center; display: block; width: 100%; }
    .status-partial { background-color: #00c0ef; color: white; padding: 5px 10px; border-radius: 5px; text-align: center; display: block; width: 100%;}
    
    .merged-cell {
        vertical-align: middle !important;
        font-weight: 500;
        background-color: #fff !important; 
        border-bottom: 1px solid #ddd;
    }
    
    div.dt-buttons { float: none !important; display: inline-block !important; text-align: center !important; margin-bottom: 5px; }
    .dataTables_wrapper .row .text-center { text-align: center !important; }
    div.dataTables_wrapper div.dataTables_filter { text-align: right; }
    div.dataTables_wrapper div.dataTables_length { text-align: left; }
    .dt-button { background-color: #f4f4f4; color: #444; border: 1px solid #ddd; padding: 5px 10px; margin: 0 2px; border-radius: 3px; }
    
    #sale_order_reward th, 
    #sale_order_reward td { 
        white-space: nowrap; 
        vertical-align: middle !important; 
    }
    #sale_order_reward th { text-align: center; }
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
                {!! Form::select('sell_list_filter_status', ['' => __('All'), 'pending' => __('Pending'), 'partial' => __('Partial'), 'completed' => __('Completed'), 'completed_atu' => __('Completed ATU')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                                
                                <th>@lang('Product Ring')</th>
                                <th>@lang('Product Prize')</th>
                                <th>@lang('Quantity')</th>
                                <th>@lang('Ring Receivable')</th>
                                <th>@lang('Ring Cash Receivable')</th>
                                <th>@lang('Ring Received')</th>
                                <th>@lang('Ring Cash Received')</th>
                                <th>@lang('Total Ring Cash(Amount)')</th>
                                
                                <th>@lang('Status')</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr class="bg-gray font-17 footer-total text-center">
                                <td colspan="7"><strong>@lang('sale.total'):</strong></td>
                                <td></td> 
                                <td></td> 
                                <td id="footer_quantity" class="text-center"></td>
                                <td id="footer_ring_receivable" class="text-right"></td>
                                <td id="footer_ring_cash_receivable" class="text-center"></td>
                                <td id="footer_ring_received" class="text-right"></td>
                                <td id="footer_ring_cash_received" class="text-left" style="font-size: 11px;"></td>
                                <td id="footer_total_ring_cash" class="text-center"></td>
                                <td></td> 
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
    const numberFmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    $('#sell_list_filter_date_range').daterangepicker({
        locale: { format: 'YYYY-MM-DD' },
        startDate: startDate, endDate: endDate,
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

    function initializeDataTable() {
        if (rewardTable) { rewardTable.destroy(); }
        
        rewardTable = $('#sale_order_reward').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            ordering: false,
            deferRender: true,
            lengthMenu: [[25, 50, 100, 200, 500, 1000, -1], [25, 50, 100, 200, 500, 1000, "All"]],
            pageLength: 25,
            dom: '<"row"<"col-md-3"l><"col-md-6 text-center"B><"col-md-3"f>>rtip',
            
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

            // --- FIXED FOOTER LOGIC START ---
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();

                // 1. Helper to clean and parse numbers safely
                var intVal = function (i) {
                    if (i === null || i === undefined) return 0;
                    
                    if (typeof i === 'number') {
                        return i;
                    }
                    
                    if (typeof i === 'string') {
                        // Remove HTML tags first
                        var clean = i.replace(/<[^>]+>/g, '');
                        // Remove 'Ring', '$', '៛', commas, spaces, and text
                        clean = clean.replace(/Ring|\$|៛|,|\s/g, '');
                        
                        // Handle dashes or empty strings
                        if (clean === '' || clean === '-') return 0;
                        
                        return parseFloat(clean) || 0;
                    }
                    return 0;
                };

                // 2. Helper to sum a specific column index for CURRENT PAGE
                var sumColumn = function(colIndex) {
                    return api.column(colIndex, { page: 'current' }).data().reduce(function (a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);
                };

                // 2b. Helper to sum only the FIRST row of each transaction group
                // Used for header-level values like Ring Cash Receivable and Total Ring Cash Amount
                // to avoid double-counting when a transaction has multiple product rows
                var sumColumnFirstRowOnly = function(colIndex) {
                    var total = 0;
                    api.rows({ page: 'current' }).every(function() {
                        var rowData = this.data();
                        if (rowData && rowData.is_first_row) {
                            total += intVal(this.cell(this.index(), colIndex).data());
                        }
                    });
                    return total;
                };

                // 3. Calculate Simple Sums
                var totalQty = sumColumn(9);                    // Quantity (per product row — correct)
                var totalRingRec = sumColumn(10);               // Ring Receivable (per product row — correct)
                var totalCashRec = sumColumnFirstRowOnly(11);   // Ring Cash Receivable (per transaction — FIX)
                var totalRingReceived = sumColumn(12);          // Ring Received (per product row — correct)
                var totalAmount = sumColumnFirstRowOnly(14);    // Total Ring Cash Amount (per transaction — FIX)

                // 4. Update Footer Display
                $(api.column(9).footer()).html(numberFmt.format(totalQty));
                $(api.column(10).footer()).html(numberFmt.format(totalRingRec) + ' Ring');
                $(api.column(11).footer()).html('$' + numberFmt.format(totalCashRec));
                $(api.column(12).footer()).html(numberFmt.format(totalRingReceived) + ' Ring');
                $(api.column(14).footer()).html('$' + numberFmt.format(totalAmount));

                // 5. Complex Calculation for "Ring Cash Received" (Column 13)
                var cashRingTotals = {};
                
                api.column(13, { page: 'current' }).data().each(function (val) {
                    if (val && typeof val === 'string' && val !== '-') {
                        // Split by comma, break tag, or newline
                        var parts = val.split(/,|<br\s*\/?>|\n/);
                        parts.forEach(function(part) {
                            // Regex to capture: Value(Symbol) = Qty
                            // Matches: "2.00($) = 1"
                            var match = part.match(/([\d\.]+)\s*\(([$៛])\)\s*=\s*([\d\.]+)/);
                            if (match) {
                                var unitKey = match[1] + '(' + match[2] + ')';
                                var qty = parseFloat(match[3]) || 0;
                                if (!cashRingTotals[unitKey]) cashRingTotals[unitKey] = 0;
                                cashRingTotals[unitKey] += qty;
                            }
                        });
                    }
                });

                var footerHtml = [];
                for (var key in cashRingTotals) {
                    footerHtml.push(key + ' = ' + cashRingTotals[key]);
                }
                $(api.column(13).footer()).html(footerHtml.join('<br>'));
            },
            // --- FIXED FOOTER LOGIC END ---

            columns: [
                {data: 'action', name: 'action', width: '4%', orderable: false, searchable: false},
                {data: 'date', name: 'date', width: '9%', render: function(d) { return d ? moment(d).format('DD-MM-YYYY HH:mm') : ''; } },
                {data: 'sales_order_no', name: 'sales_order_no', width: '7%'},
                {data: 'invoice_no', name: 'invoice_no', width: '7%'},
                {data: 'contact_name', name: 'contact_name', width: '9%'},
                {data: 'contact_mobile', name: 'contact_mobile', width: '7%'},
                {data: 'location_name', name: 'location_name', width: '7%'},
                {data: 'product_for_sale', name: 'product_for_sale'},
                {data: 'product_prize', name: 'product_prize'},
                
                // Format Raw Numbers from Controller
                {data: 'quantity', name: 'quantity', className: 'text-center', render: function(d) { return d !== '-' ? numberFmt.format(d) : '-'; } },
                {data: 'ring_receivable', name: 'ring_receivable', className: 'text-right', render: function(d) { return d !== '-' ? numberFmt.format(d) + ' Ring' : '-'; }},
                {data: 'ring_cash_receivable', name: 'ring_cash_receivable', className: 'text-center', render: function(d) { return d !== 0 && d !== '-' ? '$' + numberFmt.format(d) : '-'; }},
                {data: 'ring_received', name: 'ring_received', className: 'text-right', render: function(d) { return d !== '-' ? numberFmt.format(d) + ' Ring' : '-'; }},
                {data: 'ring_cash_received', name: 'ring_cash_received', className: 'text-left'},
                {data: 'total_ring_cash_amount', name: 'total_ring_cash_amount', className: 'text-center', render: function(d) { return d !== 0 && d !== '-' ? '$' + numberFmt.format(d) : '-'; }},
                
                {
                    data: 'status', name: 'status', width: '5%',
                    render: function(data) {
                        if (data === 'pending') return '<span class="status-pending">Pending</span>';
                        if (data === 'partial') return '<span class="status-partial">Partial</span>';
                        if (data === 'completed') return '<span class="status-completed">Completed</span>';
                        if (data === 'completed_atu') return '<span class="status-completed-atu">Completed ATU</span>';
                        return data;
                    }
                }
            ],
            
            drawCallback: function(settings) {
                var api = this.api();
                var rows = api.rows({page:'current'}).nodes();
                var data = api.rows({page:'current'}).data();
                var processedTransactions = [];
                var mergeCols = [0, 1, 2, 3, 4, 5, 6, 11, 14, 15];

                data.each(function(rowData, i) {
                    if (rowData.is_first_row && processedTransactions.indexOf(rowData.transaction_id) === -1) {
                        processedTransactions.push(rowData.transaction_id);
                        var productCount = rowData.product_count;
                        if (productCount > 1) {
                            mergeCols.forEach(function(colIndex) {
                                var cell = $(rows[i]).find('td').eq(colIndex);
                                if (cell.length > 0) {
                                    cell.attr('rowspan', productCount).addClass('merged-cell');
                                    for (var j = 1; j < productCount; j++) {
                                        if (i + j < rows.length) {
                                            $(rows[i + j]).find('td').eq(colIndex).hide();
                                        }
                                    }
                                }
                            });
                        }
                    }
                });
            },

            buttons: [
                { extend: 'csv', text: '<i class="fa fa-file-text-o"></i> Export to CSV', className: 'btn btn-default btn-sm', exportOptions: { columns: ':visible:not(:first-child)' }, action: function (e, dt, node, config) { exportToCSV(); }},
                { extend: 'excel', text: '<i class="fa fa-file-excel-o"></i> Export to Excel', className: 'btn btn-default btn-sm', exportOptions: { columns: ':visible:not(:first-child)' }, action: function (e, dt, node, config) { exportToExcel(); }},
                { extend: 'print', text: '<i class="fa fa-print"></i> Print', className: 'btn btn-default btn-sm', exportOptions: { columns: ':visible:not(:first-child)' }},
                { extend: 'colvis', text: '<i class="fa fa-columns"></i> Column visibility', className: 'btn btn-default btn-sm'}
            ]
        });
    }

    initializeDataTable();

    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_status').change(function() {
        rewardTable.ajax.reload(null, false);
    });
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        rewardTable.ajax.reload();
    });

    // --- EXPORT WITH SEARCH PARAM ---
    function getAllDataForExport(callback) {
        var params = {
            sell_list_filter_location_id: $('#sell_list_filter_location_id').val(),
            sell_list_filter_contact_id: $('#sell_list_filter_contact_id').val(),
            sell_list_filter_status: $('#sell_list_filter_status').val(),
            length: -1, start: 0, ajax: 1
        };
        // INCLUDE SEARCH VALUE
        var searchTerm = rewardTable.search();
        if(searchTerm) params['search[value]'] = searchTerm;

        if ($('#sell_list_filter_date_range').data('daterangepicker')) {
            params.start_date = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
            params.end_date = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
        }
        $.ajax({
            url: "{{ url('sales-reward') }}", type: 'GET', data: params,
            success: function(response) { callback(response.data || []); },
            error: function() { toastr.error('Failed to fetch export data'); callback([]); }
        });
    }

    function processDataForExport(data) {
        var processedData = [];
        var lastTransactionId = null;
        data.forEach(function(row) {
            var isFirst = (row.transaction_id !== lastTransactionId);
            var date = isFirst ? (row.date ? moment(row.date).format('DD-MM-YYYY HH:mm') : '') : '';
            var ringCashReceived = (row.ring_cash_received || '').replace(/<br\s*\/?>/gi, "\n"); 

            processedData.push([
                date,
                isFirst ? (row.sales_order_no || '') : '',
                isFirst ? (row.invoice_no || '') : '',
                isFirst ? (row.contact_name || '') : '',
                isFirst ? (row.contact_mobile || '') : '',
                isFirst ? (row.location_name || '') : '',
                row.product_for_sale || '',
                row.product_prize || '',
                row.quantity || '',
                row.ring_receivable || '',
                isFirst ? (row.ring_cash_receivable || '') : '',
                row.ring_received || '',
                ringCashReceived,
                isFirst ? (row.total_ring_cash_amount || '') : '',
                isFirst ? (row.status || '') : ''
            ]);
            lastTransactionId = row.transaction_id;
        });
        return processedData;
    }

    function calculateExportTotals(processedData) {
        var t = { qty: 0, ringRec: 0, cashRec: 0, ringReceived: 0, amount: 0, cashRingMap: {}, cashReceivedStr: '' };
        
        processedData.forEach(function(row) {
            var parseVal = function(val) { 
                if (!val) return 0;
                var str = String(val).replace(/[\$,]|Ring| /g, '');
                if (str === '-' || str === '') return 0;
                return parseFloat(str) || 0; 
            };
            
            t.qty += parseVal(row[8]);
            t.ringRec += parseVal(row[9]);
            // row[10] (cashRec) and row[13] (amount) are blanked on sub-rows
            // by processDataForExport, so summing them here is safe and correct.
            t.cashRec += parseVal(row[10]);
            t.ringReceived += parseVal(row[11]);
            t.amount += parseVal(row[13]);

            var cashRingStr = row[12];
            if (cashRingStr && cashRingStr !== '-') {
                cashRingStr.split('\n').forEach(function(part) {
                    var match = part.match(/([\d\.]+)\s*\(([$៛])\)\s*=\s*([\d\.]+)/);
                    if (match) {
                        var key = match[1] + '(' + match[2] + ')';
                        t.cashRingMap[key] = (t.cashRingMap[key] || 0) + parseFloat(match[3]);
                    }
                });
            }
        });

        var cashRingFooterStr = [];
        for (var key in t.cashRingMap) {
            cashRingFooterStr.push(key + ' = ' + t.cashRingMap[key]);
        }
        t.cashReceivedStr = cashRingFooterStr.join('\n');
        
        t.qty = numberFmt.format(t.qty);
        t.ringRec = numberFmt.format(t.ringRec);
        t.cashRec = numberFmt.format(t.cashRec);
        t.ringReceived = numberFmt.format(t.ringReceived);
        t.amount = numberFmt.format(t.amount);

        return t;
    }

    function exportToCSV() {
        getAllDataForExport(function(data) {
            if (data.length === 0) { toastr.warning('No data to export'); return; }
            var processed = processDataForExport(data);
            var headers = ['Date', 'Sales Order No', 'Invoice No', 'Contact Name', 'Contact Mobile', 'Location', 'Product For Sale', 'Product Prize', 'Quantity', 'Ring Receivable', 'Ring Cash Receivable', 'Ring Received', 'Ring Cash Received', 'Total Ring Cash(Amount)', 'Status'];
            
            var totals = calculateExportTotals(processed);
            var totalRow = ['TOTAL:', '', '', '', '', '', '', '',
                totals.qty, totals.ringRec, totals.cashRec, totals.ringReceived, 
                totals.cashReceivedStr.replace(/\n/g, ' | '),
                totals.amount, ''];

            var csvContent = [headers.join(',')];
            processed.forEach(function(row) {
                csvContent.push(row.map(val => '"' + String(val).replace(/"/g, '""') + '"').join(','));
            });
            csvContent.push(totalRow.map(val => '"' + String(val).replace(/"/g, '""') + '"').join(','));

            var blob = new Blob([csvContent.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url; link.download = 'reward_exchange_report.csv';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
        });
    }

    function exportToExcel() {
        if (typeof XLSX === 'undefined') { toastr.error('Excel library not loaded'); return; }
        getAllDataForExport(function(data) {
            if (data.length === 0) { toastr.warning('No data to export'); return; }
            var processed = processDataForExport(data);
            var headers = ['Date', 'Sales Order No', 'Invoice No', 'Contact Name', 'Contact Mobile', 'Location', 'Product For Sale', 'Product Prize', 'Quantity', 'Ring Receivable', 'Ring Cash Receivable', 'Ring Received', 'Ring Cash Received', 'Total Ring Cash(Amount)', 'Status'];
            
            var totals = calculateExportTotals(processed);
            var totalRow = ['TOTAL:', '', '', '', '', '', '', '',
                totals.qty, 
                totals.ringRec + ' Ring', 
                '$' + totals.cashRec, 
                totals.ringReceived + ' Ring', 
                totals.cashReceivedStr, 
                '$' + totals.amount, ''];

            var wsData = [headers].concat(processed).concat([totalRow]);
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(wsData);
            
            ws['!cols'] = [{wch: 15}, {wch: 15}, {wch: 15}, {wch: 20}, {wch: 15}, {wch: 15}, {wch: 20}, {wch: 20}, {wch: 10}, {wch: 15}, {wch: 15}, {wch: 15}, {wch: 25}, {wch: 15}, {wch: 10}];

            var merges = [];
            var mergeCols = [0, 1, 2, 3, 4, 5, 10, 13, 14];
            var txStart = 0;
            for (var i = 0; i < processed.length; i++) {
                var currentTxId = data[i].transaction_id;
                var prevTxId = (i > 0) ? data[i-1].transaction_id : null;
                if (i > 0 && currentTxId !== prevTxId) {
                    if ((i - txStart) > 1) {
                        var s_r = txStart + 1; var e_r = i;
                        mergeCols.forEach(function(c) { merges.push({ s: {r: s_r, c: c}, e: {r: e_r, c: c} }); });
                    }
                    txStart = i;
                }
                if (i === processed.length - 1 && (i - txStart + 1) > 1) {
                    var s_r = txStart + 1; var e_r = i + 1;
                    mergeCols.forEach(function(c) { merges.push({ s: {r: s_r, c: c}, e: {r: e_r, c: c} }); });
                }
            }
            var footerIdx = wsData.length - 1;
            merges.push({ s: {r: footerIdx, c: 0}, e: {r: footerIdx, c: 7} });
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
            var headers = ['Date', 'SO', 'Inv', 'Contact', 'Mobile', 'Loc', 'For Sale', 'Prod', 'Qty', 'R.Rec', 'R.Cash.Rec', 'R.Get', 'R.Cash.Get', 'Total', 'Status'];
            
            var totals = calculateExportTotals(processed);
            
            var docDefinition = {
                content: [{
                    table: {
                        headerRows: 1,
                        widths: ['6%','6%','5%','7%','6%','5%','8%','8%','4%','6%','6%','6%','9%','5%','4%'],
                        body: [headers].concat(processed).concat([{ 
                            text: 'TOTAL:', colSpan: 8, bold: true, alignment: 'center' 
                        }, {}, {}, {}, {}, {}, {}, {},
                        { text: totals.qty, bold: true },
                        { text: totals.ringRec, bold: true },
                        { text: '$' + totals.cashRec, bold: true },
                        { text: totals.ringReceived, bold: true },
                        { text: totals.cashReceivedStr, bold: true, style: {fontSize: 8} },
                        { text: '$' + totals.amount, bold: true },
                        {}])
                    },
                    layout: 'lightHorizontalLines'
                }],
                pageOrientation: 'landscape', defaultStyle: { fontSize: 7 }
            };
            pdfMake.createPdf(docDefinition).download('reward_exchange_report.pdf');
        });
    }

    $(document).on('click', '.delete-reward-exchange', function(e) {
        e.preventDefault();
        var url = $(this).data('href');
        var csrfToken = $(this).data('csrf');
        swal({
            title: "Are you sure ?", text: "This will delete the reward exchange transaction and reverse all stock changes.", icon: "warning", buttons: true, dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url, type: 'DELETE', data: { "_token": csrfToken },
                    success: function(response) {
                        if (response.success) { swal("Deleted!", response.message, "success"); rewardTable.ajax.reload(); } else { swal("Failed!", response.message || 'Error occurred', "error"); }
                    },
                    error: function(xhr) { swal("Error!", "Something went wrong", "error"); }
                });
            }
        });
    });
});
</script>
@endsection