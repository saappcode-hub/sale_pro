@extends('layouts.app')
@section('title', __('Sale Revenue and A/R'))

@section('content')

<style>
    .footer-total { background-color: #fff3cd !important; font-weight: bold; }
    .footer-total td { color: #856404 !important; }
    .footer-ar { background-color: #f8d7da !important; font-weight: bold; }
    .footer-ar td { color: #721c24 !important; }
    div.dt-buttons { float: none !important; text-align: center; margin-bottom: 10px; }
    .dt-button {
        background-color: #fff !important; border: 1px solid #ddd !important;
        color: #333 !important; padding: 5px 10px !important;
        margin-right: 5px !important; border-radius: 3px !important;
    }
    .dt-button:hover { background-color: #e6e6e6 !important; }

    /* Current month received row */
    .row-received-cur td { background-color: #d4edda !important; color: #155724 !important; }

    /* Past month A/R collected rows */
    .row-received-ar td { background-color: #e8f5e9 !important; color: #2e7d32 !important; }

    /* Outstanding A/R due cell - amber */
    .cell-ar-due { background-color: #fff3cd !important; color: #856404 !important; font-weight: bold; }

    .dash-cell { color: #ccc !important; text-align: center !important; }
    .sar-table td, .sar-table th { text-align: right; vertical-align: middle; }
    .sar-table td:first-child, .sar-table th:first-child { text-align: left; }

    /* Month range picker */
    #month_range_display { cursor: pointer; background-color: #fff; }
    .month-picker-dropdown {
        display: none; position: absolute; z-index: 9999;
        background: #fff; border: 1px solid #ddd; border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 14px 16px 10px; min-width: 340px;
    }
    .month-picker-dropdown .mp-label {
        font-size: 11px; font-weight: 700; color: #888;
        text-transform: uppercase; margin-bottom: 4px;
    }
    .month-picker-dropdown input[type="month"] {
        width: 100%; border: 1px solid #ccc;
        border-radius: 4px; padding: 5px 8px; font-size: 13px;
    }
    .month-picker-dropdown .mp-actions { margin-top: 10px; text-align: right; }
</style>

<section class="content-header">
    <h1>{{ __('Sale Revenue and A/R') }}</h1>
</section>

<section class="content">

    {{-- ══════════════════════  FILTER  ══════════════════════ --}}
    @component('components.filters', ['title' => __('report.filters')])
    @php
        $fromParts = explode('-', $fromMonthParam); // ['2026','01']
        $toParts   = explode('-', $toMonthParam);

        // Build year list: current year ± 3
        $yearNow   = (int) date('Y');
        $yearList  = range($yearNow - 3, $yearNow + 1);

        $monthNames = [
            '01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr',
            '05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug',
            '09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec',
        ];
    @endphp
    <form method="GET" action="{{ request()->url() }}" id="sar_filter_form">
        <input type="hidden" name="from_month" id="from_month_input" value="{{ $fromMonthParam }}">
        <input type="hidden" name="to_month"   id="to_month_input"   value="{{ $toMonthParam }}">
        <div class="row">
            <div class="col-md-3" style="position:relative;">
                <div class="form-group">
                    <label><strong>Month Range:</strong></label>
                    <input type="text" id="month_range_display" class="form-control" readonly
                           value="{{ $fromMonthParam }} ~ {{ $toMonthParam }}"
                           placeholder="Select month range"
                           style="cursor:pointer;">

                    <div class="month-picker-dropdown" id="month_picker_dropdown">
                        <div class="row">

                            {{-- FROM --}}
                            <div class="col-xs-6">
                                <div class="mp-label">From Month</div>
                                <div style="display:flex;gap:4px;">
                                    <select id="mp_from_month" class="form-control input-sm" style="flex:1;">
                                        @foreach($monthNames as $val => $name)
                                            <option value="{{ $val }}" {{ ($fromParts[1] ?? '01') == $val ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <select id="mp_from_year" class="form-control input-sm" style="width:80px;">
                                        @foreach($yearList as $yr)
                                            <option value="{{ $yr }}" {{ ($fromParts[0] ?? $yearNow) == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- TO --}}
                            <div class="col-xs-6">
                                <div class="mp-label">To Month</div>
                                <div style="display:flex;gap:4px;">
                                    <select id="mp_to_month" class="form-control input-sm" style="flex:1;">
                                        @foreach($monthNames as $val => $name)
                                            <option value="{{ $val }}" {{ ($toParts[1] ?? '01') == $val ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <select id="mp_to_year" class="form-control input-sm" style="width:80px;">
                                        @foreach($yearList as $yr)
                                            <option value="{{ $yr }}" {{ ($toParts[0] ?? $yearNow) == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>
                        <div class="mp-actions">
                            <button type="button" class="btn btn-default btn-sm" id="mp_cancel">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" id="mp_apply">
                                <i class="fa fa-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
    @endcomponent


    {{-- ══════════════════════  EXPORT BUTTONS  ══════════════════════ --}}
    <div style="text-align:center; margin-bottom:10px;">
        <button type="button" class="btn btn-default btn-sm" id="export_all_csv">
            <i class="fa fa-file-text-o"></i> Export to CSV
        </button>
        <button type="button" class="btn btn-default btn-sm" id="export_all_excel">
            <i class="fa fa-file-excel-o"></i> Export to Excel
        </button>
        <button type="button" class="btn btn-default btn-sm" id="export_all_print">
            <i class="fa fa-print"></i> Print
        </button>
        <button type="button" class="btn btn-default btn-sm" id="export_all_pdf">
            <i class="fa fa-file-pdf-o"></i> Export to PDF
        </button>
    </div>

    {{-- ══════════════════════  ONE BOX PER MONTH  ══════════════════════ --}}
    @foreach($monthsData as $mKey => $m)
    @php
        /* Pull month-specific data into flat vars — same names as the original blade */
        $currentLabel = $m['currentLabel'];
        $currentKey   = $m['currentKey'];
        $arLabels     = $m['arLabels'];
        $saleRevenue  = $m['saleRevenue'];
        $receivedAR   = $m['receivedAR'];
        $arBalance    = $m['arBalance'];
        $totalRevenue = $m['totalRevenue'];
        $totalAR      = $m['totalAR'];

        /* Unique table ID per month */
        $tableId = 'sar_table_' . str_replace('-', '_', $mKey);
    @endphp

    @component('components.widget', ['class' => 'box-primary'])

        {{-- Legend (same as original) --}}
        <div style="margin-bottom:10px; font-size:12px; color:#555;">
            <span style="display:inline-block;width:14px;height:14px;background:#d4edda;border:1px solid #c3e6cb;vertical-align:middle;margin-right:4px;"></span> Received (collected this month) &nbsp;&nbsp;
            <span style="display:inline-block;width:14px;height:14px;background:#fff3cd;border:1px solid #ffc107;vertical-align:middle;margin-right:4px;"></span> A/R currently outstanding (due) &nbsp;&nbsp;
            <span style="display:inline-block;width:14px;height:14px;background:#f8d7da;border:1px solid #f5c6cb;vertical-align:middle;margin-right:4px;"></span> Total A/R
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped sar-table" id="{{ $tableId }}" style="width:100%;">
                <thead>
                    <tr>
                        {{-- Row label --}}
                        <th style="text-align:left; min-width:220px; vertical-align:middle;"></th>

                        {{-- Current month value column --}}
                        <th style="min-width:130px;">{{ $currentLabel }}</th>

                        {{-- A/R columns: only show months with AR due > 0 OR received > 0 --}}
                        @foreach($arLabels as $key => $label)
                            @php
                                $colBalance  = $arBalance[$key]  ?? 0;
                                $colReceived = $receivedAR[$key] ?? 0;
                                $showCol     = ($colBalance > 0 || $key === $currentKey);
                            @endphp
                            @if($showCol)
                                <th style="min-width:130px;">{{ $label }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>

                <tbody>

                    {{--
                        ── Sale Revenue in MM/YYYY ──
                    --}}
                    <tr>
                        <td><strong>Sale Revenue in {{ $currentLabel }}</strong></td>
                        <td><strong>${{ number_format($saleRevenue, 2) }}</strong></td>
                        @foreach($arLabels as $key => $label)
                            @php
                                $colBalance  = $arBalance[$key]  ?? 0;
                                $colReceived = $receivedAR[$key] ?? 0;
                                $showCol     = ($colBalance > 0 || $key === $currentKey);
                            @endphp
                            @if($showCol)
                                <td class="dash-cell">&mdash;</td>
                            @endif
                        @endforeach
                    </tr>

                    {{--
                        ── Received rows — one per month ──
                    --}}
                    @foreach($arLabels as $rowKey => $rowLabel)
                        @php
                            $mDate       = \Carbon\Carbon::createFromFormat('Y-m', $rowKey);
                            $isCurrentMo = ($rowKey === $currentKey);
                            $rowLabel    = $isCurrentMo
                                            ? 'Received in '  . $mDate->format('m/Y')
                                            : 'Received A/R ' . $mDate->format('m/Y');
                            $rowClass    = $isCurrentMo ? 'row-received-cur' : 'row-received-ar';
                            $rowReceived = $receivedAR[$rowKey] ?? 0;
                            $rowDue      = $arBalance[$rowKey]  ?? 0;
                            $showRow     = ($isCurrentMo || $rowDue > 0 || $rowReceived > 0);
                        @endphp
                        @if($showRow)
                            <tr class="{{ $rowClass }}">
                                <td>{{ $rowLabel }}</td>
                                <td>${{ number_format($rowReceived, 2) }}</td>

                                @foreach($arLabels as $colKey => $colLabel)
                                    @php
                                        $colBalance  = $arBalance[$colKey]  ?? 0;
                                        $colReceived = $receivedAR[$colKey] ?? 0;
                                        $showCol     = ($colBalance > 0 || $colKey === $currentKey);
                                    @endphp
                                    @if($showCol)
                                        @if($colKey === $rowKey)
                                            {{-- Matching A/R column --}}
                                            @php
                                                $arMonthDate = \Carbon\Carbon::createFromFormat('Y-m', $colKey);
                                                $arStartDate = $arMonthDate->copy()->startOfMonth()->format('Y-m-d');
                                                $arEndDate   = $arMonthDate->copy()->endOfMonth()->format('Y-m-d');
                                                $arUrl = action([\App\Http\Controllers\SellController::class, 'index'])
                                                       . '?ar_month=' . $colKey
                                                       . '&start_date=' . $arStartDate
                                                       . '&end_date='   . $arEndDate
                                                       . '&payment_status=due,partial';
                                            @endphp
                                            <td class="cell-ar-due">
                                                @if($rowDue > 0)
                                                    <a href="{{ $arUrl }}"
                                                       style="color: #856404; text-decoration: none; font-weight: bold; display: block;"
                                                       title="Click to view outstanding invoices for {{ $mDate->format('m/Y') }}">
                                                        ${{ number_format($rowDue, 2) }}
                                                    </a>
                                                @else
                                                    ${{ number_format($rowDue, 2) }}
                                                @endif
                                            </td>
                                        @else
                                            <td class="dash-cell">&mdash;</td>
                                        @endif
                                    @endif
                                @endforeach
                            </tr>
                        @endif
                    @endforeach

                    {{--
                        ── Total Revenue ──
                    --}}
                    <tr class="footer-total">
                        <td><strong>Total Revenue</strong></td>
                        <td><strong>${{ number_format($totalRevenue, 2) }}</strong></td>
                        @foreach($arLabels as $key => $label)
                            @php
                                $colBalance  = $arBalance[$key]  ?? 0;
                                $colReceived = $receivedAR[$key] ?? 0;
                                $showCol     = ($colBalance > 0 || $key === $currentKey);
                            @endphp
                            @if($showCol)
                                <td class="dash-cell">&mdash;</td>
                            @endif
                        @endforeach
                    </tr>

                    {{--
                        ── Total A/R ──
                    --}}
                    <tr class="footer-ar">
                        <td><strong>Total A / R</strong></td>
                        <td><strong>${{ number_format($totalAR, 2) }}</strong></td>
                        @foreach($arLabels as $key => $label)
                            @php
                                $colBalance  = $arBalance[$key]  ?? 0;
                                $colReceived = $receivedAR[$key] ?? 0;
                                $showCol     = ($colBalance > 0 || $key === $currentKey);
                            @endphp
                            @if($showCol)
                                @php
                                    $arMonthDate = \Carbon\Carbon::createFromFormat('Y-m', $key);
                                    $arStartDate = $arMonthDate->copy()->startOfMonth()->format('Y-m-d');
                                    $arEndDate   = $arMonthDate->copy()->endOfMonth()->format('Y-m-d');
                                    $arUrl = action([\App\Http\Controllers\SellController::class, 'index'])
                                           . '?ar_month=' . $key
                                           . '&start_date=' . $arStartDate
                                           . '&end_date='   . $arEndDate
                                           . '&payment_status=due,partial';
                                @endphp
                                <td>
                                    @if($colBalance > 0)
                                        <a href="{{ $arUrl }}"
                                           style="color: #721c24; text-decoration: none; font-weight: bold; display: block;"
                                           title="Click to view outstanding invoices for {{ $arMonthDate->format('m/Y') }}">
                                            <strong>${{ number_format($colBalance, 2) }}</strong>
                                        </a>
                                    @else
                                        <strong>${{ number_format($colBalance, 2) }}</strong>
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endcomponent

    @endforeach

</section>

@endsection

@section('javascript')
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {

    // ── Month range picker ────────────────────────────────────────
    // Strip query params from URL so F5/refresh always loads default 3 months
    history.replaceState({}, '', window.location.pathname);

    $('#month_range_display').on('click', function(e) {
        e.stopPropagation();
        $('#month_picker_dropdown').toggle();
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#month_picker_dropdown, #month_range_display').length) {
            $('#month_picker_dropdown').hide();
        }
    });
    $('#mp_cancel').on('click', function() {
        $('#month_picker_dropdown').hide();
    });

    // ── Enforce To >= From: update To selects when From changes ──
    function syncToMinimum() {
        var fromYear  = parseInt($('#mp_from_year').val());
        var fromMonth = parseInt($('#mp_from_month').val());

        // Year: disable To year options < fromYear
        $('#mp_to_year option').each(function() {
            var yr = parseInt($(this).val());
            $(this).prop('disabled', yr < fromYear);
        });
        // If current To year < fromYear, snap it up
        if (parseInt($('#mp_to_year').val()) < fromYear) {
            $('#mp_to_year').val(fromYear);
        }

        // Month: if same year, disable To month options < fromMonth
        var toYear = parseInt($('#mp_to_year').val());
        $('#mp_to_month option').each(function() {
            var mo = parseInt($(this).val());
            $(this).prop('disabled', toYear === fromYear && mo < fromMonth);
        });
        // If current To month is now disabled, snap to fromMonth
        if ($('#mp_to_month option:selected').prop('disabled')) {
            $('#mp_to_month').val($('#mp_from_month').val());
        }
    }
    $('#mp_from_month, #mp_from_year').on('change', syncToMinimum);
    $('#mp_to_year').on('change', syncToMinimum);
    syncToMinimum(); // run once on load
    $('#mp_apply').on('click', function() {
        var fromYear  = $('#mp_from_year').val();
        var fromMonth = $('#mp_from_month').val();
        var toYear    = $('#mp_to_year').val();
        var toMonth   = $('#mp_to_month').val();

        var from = fromYear + '-' + fromMonth;
        var to   = toYear   + '-' + toMonth;

        // Validate from <= to
        if (from > to) {
            toastr.warning('From Month cannot be after To Month.');
            return;
        }

        $('#from_month_input').val(from);
        $('#to_month_input').val(to);
        $('#month_range_display').val(from + ' ~ ' + to);
        $('#month_picker_dropdown').hide();
        $('#sar_filter_form').submit();
    });

    // ── Advanced exportOptions (same as original) ─────────────────
    var exportOpts = {
        columns: ':visible',
        rows:    ':visible',
        format: {
            body: function (data, row, column, node) {
                // 1. Strip HTML tags to get pure text
                var text = data ? data.toString().replace(/<[^>]*>/g, '').trim() : '';

                // 2. Fix the HTML dash entity if present
                if (text === '&mdash;') {
                    return '-';
                }

                // 3. IMPORTANT TRICK: Prevent Excel/CSV from stripping commas and $ symbols!
                // DataTables normally detects numbers and removes formatting for exports.
                // By prepending a zero-width space (\u200B), we force the spreadsheet
                // software (Excel/Google Sheets) to treat it strictly as TEXT.
                // This perfectly preserves the "$194,494.27" exact visual format.
                if (text.indexOf('$') > -1) {
                    return '\u200B' + text;
                }

                return text;
            }
        }
    };

    // ── Init DataTable for EACH month table (same config as original) ──
    @foreach($monthsData as $mKey => $m)
    @php $tableId = 'sar_table_' . str_replace('-', '_', $mKey); @endphp
    $('#{{ $tableId }}').DataTable({
        paging:    false,
        searching: false,
        ordering:  false,
        info:      false,
        dom: 'rt',
        buttons: [
            {
                extend:        'csv',
                text:          '<i class="fa fa-file-text-o"></i> Export to CSV',
                className:     'btn btn-default btn-sm',
                exportOptions: exportOpts
            },
            {
                extend:        'excel',
                text:          '<i class="fa fa-file-excel-o"></i> Export to Excel',
                className:     'btn btn-default btn-sm',
                title:         'Sale Revenue and A/R - {{ $m['currentLabel'] }}',
                exportOptions: exportOpts,
                customize: function(xlsx) {
                    // Highlight the Total rows in the Excel file
                    var sheet = xlsx.xl.worksheets['sheet1.xml'];
                    var rows  = $('row', sheet);
                    var total = rows.length;
                    // Second-to-last row = Total Revenue → amber fill
                    $('c', rows.eq(total - 2)).attr('s', '42');
                    // Last row = Total A/R → red fill
                    $('c', rows.eq(total - 1)).attr('s', '42');
                }
            },
            {
                extend:        'print',
                text:          '<i class="fa fa-print"></i> Print',
                className:     'btn btn-default btn-sm',
                title:         'Sale Revenue and A/R - {{ $m['currentLabel'] }}',
                exportOptions: exportOpts
            },
            {
                extend:    'colvis',
                text:      '<i class="fa fa-columns"></i> Column visibility',
                className: 'btn btn-default btn-sm'
            },
            {
                extend:        'pdf',
                text:          '<i class="fa fa-file-pdf-o"></i> Export to PDF',
                className:     'btn btn-default btn-sm',
                title:         'Sale Revenue and A/R - {{ $m['currentLabel'] }}',
                exportOptions: exportOpts,
                customize: function(doc) {
                    // Make the last two rows bold/colored in the PDF
                    var body = doc.content[1].table.body;
                    var last = body.length - 1;
                    for (var i = 0; i < body[last].length; i++) {
                        body[last][i].bold      = true;
                        body[last][i].fillColor = '#f8d7da';
                        body[last][i].color     = '#721c24';
                    }
                    var prev = body[last - 1];
                    for (var j = 0; j < prev.length; j++) {
                        prev[j].bold      = true;
                        prev[j].fillColor = '#fff3cd';
                        prev[j].color     = '#856404';
                    }
                }
            }
        ]
    });
    @endforeach



    // ── Collect all table IDs in order ───────────────────────────
    var allTableIds = [
        @foreach($monthsData as $mKey => $m)
        { id: '{{ 'sar_table_' . str_replace('-', '_', $mKey) }}', label: 'Sale Revenue in {{ $m['currentLabel'] }}' },
        @endforeach
    ];

    function getAllRows(tableId) {
        var rows = [];
        // header
        var headers = [];
        $('#' + tableId + ' thead tr th').each(function() { headers.push($(this).text().trim()); });
        rows.push(headers);
        // body
        $('#' + tableId + ' tbody tr').each(function() {
            var row = [];
            $(this).find('td').each(function() {
                var t = $(this).text().trim();
                row.push(t === '—' ? '-' : t);
            });
            rows.push(row);
        });
        return rows;
    }

    // ── Export All CSV ────────────────────────────────────────────
    $('#export_all_csv').on('click', function() {
        var lines = [];
        allTableIds.forEach(function(tbl) {
            lines.push('"' + tbl.label + '"');
            getAllRows(tbl.id).forEach(function(row) {
                lines.push(row.map(function(c) { return '"' + c.replace(/"/g, '""') + '"'; }).join(','));
            });
            lines.push('');
        });
        var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'SaleRevenueAR_All.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });

    // ── Export All Excel (real .xlsx via SheetJS) ────────────────
    $('#export_all_excel').on('click', function() {
        var wb = XLSX.utils.book_new();

        allTableIds.forEach(function(tbl) {
            var wsData = [];
            // Header row
            var headers = [];
            $('#' + tbl.id + ' thead tr th').each(function() {
                headers.push($(this).text().trim());
            });
            wsData.push(headers);
            // Body rows
            $('#' + tbl.id + ' tbody tr').each(function() {
                var row = [];
                $(this).find('td').each(function() {
                    var t = $(this).text().trim();
                    row.push(t === '—' ? '-' : t);
                });
                wsData.push(row);
            });

            var ws = XLSX.utils.aoa_to_sheet(wsData);

            // Auto column width
            var colWidths = headers.map(function(h) { return { wch: Math.max(h.length, 14) }; });
            ws['!cols'] = colWidths;

            // Sheet name max 31 chars, no special chars
            var sheetName = tbl.label.replace('Sale Revenue in ', '').replace(/[\/\\?*\[\]]/g, '-').substring(0, 31);
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
        });

        XLSX.writeFile(wb, 'SaleRevenueAR_All.xlsx');
    });

    // ── Export All Print ──────────────────────────────────────────
    $('#export_all_print').on('click', function() {
        var html = '<html><head><title>Sale Revenue and A/R - All Months</title>'
                 + '<style>body{font-family:Arial,sans-serif;font-size:11px}'
                 + 'h3{margin:16px 0 4px}'
                 + 'table{border-collapse:collapse;width:100%;margin-bottom:24px}'
                 + 'th,td{border:1px solid #ccc;padding:5px 7px;text-align:right}'
                 + 'th:first-child,td:first-child{text-align:left}'
                 + 'tr:last-child td{font-weight:bold;background:#f8d7da}'
                 + 'tr:nth-last-child(2) td{font-weight:bold;background:#fff3cd}'
                 + '</style></head><body>';
        allTableIds.forEach(function(tbl) {
            html += '<h3>' + tbl.label + '</h3>' + $('#' + tbl.id).prop('outerHTML');
        });
        html += '</body></html>';
        var win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
    });

    // ── Export All PDF ────────────────────────────────────────────
    $('#export_all_pdf').on('click', function() {
        if (typeof pdfMake === 'undefined') { toastr.error('PDF library not loaded'); return; }

        var docContent = [{ text: 'Sale Revenue and A/R — All Months', style: 'title' }];

        allTableIds.forEach(function(tbl) {
            docContent.push({ text: tbl.label, style: 'header' });

            var headers = [];
            $('#' + tbl.id + ' thead tr th').each(function() {
                headers.push({ text: $(this).text().trim(), bold: true, fillColor: '#4472c4', color: '#fff', fontSize: 7 });
            });
            var body = [headers];
            var rowCount = 0;
            var totalRows = $('#' + tbl.id + ' tbody tr').length;
            $('#' + tbl.id + ' tbody tr').each(function() {
                rowCount++;
                var isLast   = rowCount === totalRows;
                var isSecLast= rowCount === totalRows - 1;
                var row = [];
                $(this).find('td').each(function(i) {
                    row.push({
                        text:      $(this).text().trim(),
                        bold:      isLast || isSecLast,
                        fillColor: isLast ? '#f8d7da' : (isSecLast ? '#fff3cd' : null),
                        color:     isLast ? '#721c24' : (isSecLast ? '#856404' : null),
                        alignment: i === 0 ? 'left' : 'right',
                        fontSize:  7
                    });
                });
                body.push(row);
            });

            docContent.push({
                table: { headerRows: 1, body: body },
                layout: 'lightHorizontalLines',
                margin: [0, 0, 0, 16]
            });
        });

        pdfMake.createPdf({
            content: docContent,
            styles: {
                title:  { fontSize: 14, bold: true, margin: [0, 0, 0, 10] },
                header: { fontSize: 11, bold: true, margin: [0, 8, 0, 4], color: '#333' }
            },
            defaultStyle: { fontSize: 7 },
            pageOrientation: 'landscape'
        }).download('SaleRevenueAR_All.pdf');
    });

});
</script>
@endsection