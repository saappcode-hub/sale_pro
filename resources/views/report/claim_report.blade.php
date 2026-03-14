@extends('layouts.app')
@section('title', __('Claim Report'))

@section('content')

{{-- Style to match the layout in your image --}}
<style>
    .footer-total {
        background-color: #d2d6de !important;
        font-weight: bold;
    }
    .footer-total td {
        color: black !important;
    }
    /* Center the buttons in the middle of the toolbar */
    div.dt-buttons {
        float: none !important;
        text-align: center;
        margin-bottom: 10px;
    }
    /* Adjust button styles */
    .dt-button {
        background-color: #fff !important;
        border: 1px solid #ddd !important;
        color: #333 !important;
        padding: 5px 10px !important;
        margin-right: 5px !important;
        border-radius: 3px !important;
    }
    .dt-button:hover {
        background-color: #e6e6e6 !important;
    }
</style>

<section class="content-header">
    <h1>{{ __('Claim Report')}}</h1>
</section>

<section class="content">
    
    @component('components.filters', ['title' => __('report.filters')])
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
                {!! Form::label('claim_report_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'claim_report_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="claim_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align: middle;">Name Wholesale</th>
                        <th rowspan="2" style="vertical-align: middle;">Address</th>
                        <th rowspan="2" style="vertical-align: middle;">Phone</th>
                        <th rowspan="2" style="vertical-align: middle;">Contact ID</th>
                        
                        <th colspan="2" class="text-center">GBS Sale</th>
                        <th colspan="2" class="text-center">BSP Sale</th>
                        <th colspan="2" class="text-center">Idol Sale</th>
                        <th colspan="2" class="text-center">FYO 110ml Sale</th>
                        <th colspan="2" class="text-center">FYO 180ml Sale</th>
                        <th colspan="2" class="text-center">Fmix</th>
                        <th colspan="2" class="text-center">FA + FG</th>
                        <th colspan="2" class="text-center">FS+FW+Lychee</th>
                        
                        <th rowspan="2" style="vertical-align: middle;">Total</th>
                    </tr>
                    <tr>
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                        
                        <th>BUY+SCHEME</th>
                        <th>CLAIM</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr class="bg-gray font-17 footer-total">
                        <td colspan="4" class="text-center"><strong>TOTAL:</strong></td>
                        <td id="footer_gbs_buy">0</td>
                        <td id="footer_gbs_claim">0</td>
                        <td id="footer_bsp_buy">0</td>
                        <td id="footer_bsp_claim">0</td>
                        <td id="footer_idol_buy">0</td>
                        <td id="footer_idol_claim">0</td>
                        <td id="footer_fyo110_buy">0</td>
                        <td id="footer_fyo110_claim">0</td>
                        <td id="footer_fyo180_buy">0</td>
                        <td id="footer_fyo180_claim">0</td>
                        <td id="footer_fmix_buy">0</td>
                        <td id="footer_fmix_claim">0</td>
                        <td id="footer_fafg_buy">0</td>
                        <td id="footer_fafg_claim">0</td>
                        <td id="footer_fsfwlychee_buy">0</td>
                        <td id="footer_fsfwlychee_claim">0</td>
                        <td id="footer_total">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent

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
   $(document).ready( function(){
    var claim_report_table;

    // Date Picker Settings
    if($('#claim_report_date_range').length == 1){
        $('#claim_report_date_range').daterangepicker(
            dateRangeSettings, 
            function(start, end) {
                $('#claim_report_date_range').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
                claim_report_table.ajax.reload();
            }
        );
        
        // Set Default Date (This Month)
        $('#claim_report_date_range').val(
            moment().startOf('month').format(moment_date_format) + ' ~ ' + moment().endOf('month').format(moment_date_format)
        );
    }

    // Initialize DataTable
    claim_report_table = $('#claim_report_table').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        ordering: false, 
        pageLength: -1, // Show all records
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: '<"row"<"col-md-3"l><"col-md-6 text-center"B><"col-md-3"f>>rtip',
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fa fa-file-text-o"></i> Export to CSV',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible' }
            },
            {
                text: '<i class="fa fa-file-excel-o"></i> Export to Excel',
                className: 'btn btn-default btn-sm',
                action: function ( e, dt, node, config ) {
                    exportClaimReportToExcel();
                }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> Print',
                className: 'btn btn-default btn-sm',
                exportOptions: { columns: ':visible' }
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
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        ajax: {
            url: "{{route('reports.claim-report')}}",
            data: function(d) {
                var start = '';
                var end = '';
                if($('#claim_report_date_range').val()){
                    start = $('input#claim_report_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    end = $('input#claim_report_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                }
                d.start_date = start;
                d.end_date = end;
                d.customer_id = $('#customer_id').val();
            }
        },
        columns: [
            { data: 'name_wholesale', name: 'name_wholesale' },
            { data: 'address', name: 'address' }, 
            { data: 'phone', name: 'phone' },
            { data: 'contact_id', name: 'contact_id' },
            
            // GBS
            { data: 'gbs_buy', name: 'gbs_buy', searchable: false },
            { data: 'gbs_claim', name: 'gbs_claim', searchable: false }, 
            
            // BSP
            { data: 'bsp_buy', name: 'bsp_buy', searchable: false },
            { data: 'bsp_claim', name: 'bsp_claim', searchable: false }, 
            
            // Idol
            { data: 'idol_buy', name: 'idol_buy', searchable: false },
            { data: 'idol_claim', name: 'idol_claim', searchable: false }, 
            
            // FYO 110ml
            { data: 'fyo110_buy', name: 'fyo110_buy', searchable: false },
            { data: 'fyo110_claim', name: 'fyo110_claim', searchable: false }, 

            // FYO 180ml
            { data: 'fyo180_buy', name: 'fyo180_buy', searchable: false },
            { data: 'fyo180_claim', name: 'fyo180_claim', searchable: false }, 

            // Fmix
            { data: 'fmix_buy', name: 'fmix_buy', searchable: false },
            { data: 'fmix_claim', name: 'fmix_claim', searchable: false },

            // FA + FG
            { data: 'fafg_buy', name: 'fafg_buy', searchable: false },
            { data: 'fafg_claim', name: 'fafg_claim', searchable: false },

            // FS+FW+Lychee
            { data: 'fsfwlychee_buy', name: 'fsfwlychee_buy', searchable: false },
            { data: 'fsfwlychee_claim', name: 'fsfwlychee_claim', searchable: false },

            // Total
            { data: 'total_qty', name: 'total_qty', searchable: false }
        ],
        fnDrawCallback: function(oSettings) {
            var json = oSettings.json;
            if(json && json.footer_gbs_buy !== undefined){
                $('#footer_gbs_buy').text(json.footer_gbs_buy);
                $('#footer_gbs_claim').text(json.footer_gbs_claim);
                $('#footer_bsp_buy').text(json.footer_bsp_buy);
                $('#footer_bsp_claim').text(json.footer_bsp_claim);
                $('#footer_idol_buy').text(json.footer_idol_buy);
                $('#footer_idol_claim').text(json.footer_idol_claim);
                $('#footer_fyo110_buy').text(json.footer_fyo110_buy);
                $('#footer_fyo110_claim').text(json.footer_fyo110_claim);
                $('#footer_fyo180_buy').text(json.footer_fyo180_buy);
                $('#footer_fyo180_claim').text(json.footer_fyo180_claim);
                $('#footer_fmix_buy').text(json.footer_fmix_buy);
                $('#footer_fmix_claim').text(json.footer_fmix_claim);
                $('#footer_fafg_buy').text(json.footer_fafg_buy);
                $('#footer_fafg_claim').text(json.footer_fafg_claim);
                $('#footer_fsfwlychee_buy').text(json.footer_fsfwlychee_buy);
                $('#footer_fsfwlychee_claim').text(json.footer_fsfwlychee_claim);
                $('#footer_total').html('<strong>' + json.footer_total + '</strong>');
            } else {
                // Reset to 0 if no data
                $('#footer_gbs_buy').text('0');
                $('#footer_gbs_claim').text('0');
                $('#footer_bsp_buy').text('0');
                $('#footer_bsp_claim').text('0');
                $('#footer_idol_buy').text('0');
                $('#footer_idol_claim').text('0');
                $('#footer_fyo110_buy').text('0');
                $('#footer_fyo110_claim').text('0');
                $('#footer_fyo180_buy').text('0');
                $('#footer_fyo180_claim').text('0');
                $('#footer_fmix_buy').text('0');
                $('#footer_fmix_claim').text('0');
                $('#footer_fafg_buy').text('0');
                $('#footer_fafg_claim').text('0');
                $('#footer_fsfwlychee_buy').text('0');
                $('#footer_fsfwlychee_claim').text('0');
                $('#footer_total').html('<strong>0</strong>');
            }
            __currency_convert_recursively($('#claim_report_table'));
        }
    });

    // Filter Change Event
    $('#customer_id').change(function(){
        claim_report_table.ajax.reload();
    });

    $('#claim_report_date_range').change(function(){
        claim_report_table.ajax.reload();
    });

    // --- Custom Excel Export Function (Matches Display Format) ---
    function exportClaimReportToExcel() {
        var params = claim_report_table.ajax.params();
        params.length = -1; 

        var dtSearch = claim_report_table.search();
        if(dtSearch) {
            params.search = { value: dtSearch };
        }

        $.ajax({
            url: "{{route('reports.claim-report')}}",
            type: 'GET',
            data: params,
            success: function(response) {
                var data = response.data;
                var footer = response; 

                var header1 = [
                    "Name Wholesale", "Address", "Phone", "Contact ID",
                    "GBS Sale", "", 
                    "BSP Sale", "",
                    "Idol Sale", "",
                    "FYO 110ml Sale", "",
                    "FYO 180ml Sale", "",
                    "Fmix", "",
                    "FA + FG", "",
                    "FS+FW+Lychee", "",
                    "Total"
                ];

                var header2 = [
                    "", "", "", "", 
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "BUY+SCHEME", "CLAIM",
                    "" 
                ];

                var excelRows = data.map(function(row) {
                    return [
                        row.name_wholesale,
                        row.address,
                        row.phone,
                        row.contact_id,
                        parseFloat(row.gbs_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.gbs_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.bsp_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.bsp_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.idol_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.idol_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.fyo110_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.fyo110_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.fyo180_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.fyo180_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.fmix_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.fmix_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.fafg_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.fafg_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.fsfwlychee_buy.replace(/,/g, '')) || 0,
                        parseFloat(row.fsfwlychee_claim.replace(/,/g, '')) || 0,
                        parseFloat(row.total_qty.toString().replace(/<[^>]*>/g, '').replace(/,/g, '')) || 0
                    ];
                });

                var footerRow = [
                    "TOTAL:", "", "", "",
                    parseFloat(footer.footer_gbs_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_gbs_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_bsp_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_bsp_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_idol_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_idol_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fyo110_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fyo110_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fyo180_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fyo180_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fmix_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fmix_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fafg_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fafg_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fsfwlychee_buy.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_fsfwlychee_claim.replace(/,/g, '')) || 0,
                    parseFloat(footer.footer_total.replace(/,/g, '')) || 0
                ];

                var ws_data = [header1, header2].concat(excelRows);
                ws_data.push(footerRow);

                var ws = XLSX.utils.aoa_to_sheet(ws_data);

                ws['!merges'] = [
                    { s: {r:0, c:0}, e: {r:1, c:0} }, // Name Wholesale
                    { s: {r:0, c:1}, e: {r:1, c:1} }, // Address
                    { s: {r:0, c:2}, e: {r:1, c:2} }, // Phone
                    { s: {r:0, c:3}, e: {r:1, c:3} }, // Contact ID
                    { s: {r:0, c:4}, e: {r:0, c:5} }, // GBS Sale
                    { s: {r:0, c:6}, e: {r:0, c:7} }, // BSP Sale
                    { s: {r:0, c:8}, e: {r:0, c:9} }, // Idol Sale
                    { s: {r:0, c:10}, e: {r:0, c:11} }, // FYO 110ml Sale
                    { s: {r:0, c:12}, e: {r:0, c:13} }, // FYO 180ml Sale
                    { s: {r:0, c:14}, e: {r:0, c:15} }, // Fmix
                    { s: {r:0, c:16}, e: {r:0, c:17} }, // FA + FG
                    { s: {r:0, c:18}, e: {r:0, c:19} }, // FS+FW+Lychee
                    { s: {r:0, c:20}, e: {r:1, c:20} }, // Total
                    { s: {r: ws_data.length-1, c:0}, e: {r: ws_data.length-1, c:3} } // Footer TOTAL label
                ];

                ws['!cols'] = [
                    {wch: 25}, {wch: 20}, {wch: 15}, {wch: 10}, 
                    {wch: 12}, {wch: 10}, {wch: 12}, {wch: 10}, 
                    {wch: 12}, {wch: 10}, {wch: 12}, {wch: 10}, 
                    {wch: 12}, {wch: 10}, {wch: 12}, {wch: 10}, 
                    {wch: 12}, {wch: 10}, {wch: 12}, {wch: 10}, 
                    {wch: 12}
                ];

                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Claim Report");
                XLSX.writeFile(wb, "Claim_Report.xlsx");
            },
            error: function() {
                alert('Error exporting data');
            }
        });
    }
});
</script>
@endsection