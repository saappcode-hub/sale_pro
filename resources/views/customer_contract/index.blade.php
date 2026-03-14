@extends('layouts.app')
@section('title', 'List Contract')

@section('content')

<section class="content-header">
    <h1>List Contract
        <small>Manage and view all customer contracts</small>
    </h1>
</section>

<style>
    /* Force vertical middle alignment for merged rows */
    .vertical-align-middle {
        vertical-align: middle !important;
    }
    /* Center align all table headers */
    #all_customer_contract_table th {
        text-align: center !important;
        vertical-align: middle !important;
    }
    /* Ensure text wrapping for long product names in center mode */
    #all_customer_contract_table td {
        white-space: normal;
    }
</style>

<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'All Contracts'])
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="all_customer_contract_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Contact Name</th>
                        <th>Ref. No.</th>
                        <th>Contract Name</th>
                        <th>Period</th>
                        <th>Product Targets</th>
                        <th>Progress</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Added By</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent

    <div class="modal fade customer_contract_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

</section>
@endsection
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
@section('javascript')
<script>
    $(document).ready(function(){
        
        if (typeof XLSX === 'undefined') {
            console.warn('SheetJS (XLSX) library not loaded. Excel export might not work.');
        }

        var table = $('#all_customer_contract_table');

        var all_customer_contract_table = table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ action([\App\Http\Controllers\CustomerContractController::class, 'getAllContracts']) }}",
                error: function (xhr) { console.log(xhr); }
            },
            // Order by Contract ID to ensure merging works correctly
            order: [[1, 'desc']], 
            dom: '<"row"<"col-md-3"l><"col-md-6 text-center"B><"col-md-3"f>>rtip',
            
            buttons: [
                {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i> Export to CSV',
                    className: 'btn-default btn-sm',
                    action: function (e, dt, node, config) { exportContractWithMerges(dt, 'csv'); }
                },
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Export to Excel',
                    className: 'btn-default btn-sm',
                    action: function (e, dt, node, config) { exportContractWithMerges(dt, 'excel'); }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    className: 'btn-default btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fa fa-columns"></i> Column Visibility',
                    className: 'btn-default btn-sm'
                }
            ],
            
            // UPDATED: Added 'text-center' to all columns
           columns: [
                { data: 'contact_name', name: 'contacts.name', className: 'text-center vertical-align-middle' }, 
                { data: 'reference_no', name: 'reference_no', className: 'text-center vertical-align-middle' },
                { data: 'contract_name', name: 'contract_name', className: 'text-center vertical-align-middle' },
                { data: 'period', name: 'start_date', searchable: false, className: 'text-center vertical-align-middle' },
                { data: 'product_name', name: 'products.name', className: 'text-center vertical-align-middle' }, 
                
                // --- UPDATED PROGRESS COLUMN ---
                { 
                    data: 'progress', 
                    name: 'progress', 
                    searchable: false, 
                    orderable: false, 
                    className: 'text-center vertical-align-middle',
                    render: function(data, type, row) {
                        // Create a clickable link
                        // We use row.contract_id provided by the controller
                        var url = "/customer-contracts/related-sales/" + row.contract_id;
                        return '<a href="#" data-href="' + url + '" class="view_related_sales_modal" style="text-decoration: underline; font-weight: bold;">' + data + '</a>';
                    }
                },
                // -------------------------------

                { data: 'total_contract_value', name: 'total_contract_value', className: 'text-center vertical-align-middle' },
                { data: 'status', name: 'status', orderable: false, searchable: false, className: 'text-center vertical-align-middle' },
                { data: 'added_by', name: 'users.first_name', className: 'text-center vertical-align-middle' }
            ],

            // VISUAL MERGING LOGIC
            drawCallback: function(settings) {
                var api = this.api();
                var rows = api.rows({ page: 'current' }).nodes();
                var data = api.rows({ page: 'current' }).data();

                // Merge Columns: Contact(0), Ref(1), Name(2), Period(3), Value(6), Status(7), Added(8)
                // SKIP: Product(4) and Progress(5)
                var mergeColumns = [0, 1, 2, 3, 6, 7, 8]; 

                var currentContractId = null;
                var startRow = null;
                var rowCount = 0;

                data.each(function(rowData, index) {
                    var contractId = rowData.contract_id; 

                    if (currentContractId !== contractId) {
                        if (currentContractId !== null && rowCount > 1) {
                            mergeColumns.forEach(function(colIndex) {
                                var cell = $(rows[startRow]).find('td').eq(colIndex);
                                if (cell.length > 0) {
                                    cell.attr('rowspan', rowCount);
                                    for (var i = 1; i < rowCount; i++) {
                                        $(rows[startRow + i]).find('td').eq(colIndex).hide();
                                    }
                                }
                            });
                        }
                        currentContractId = contractId;
                        startRow = index;
                        rowCount = 1;
                    } else {
                        rowCount++;
                    }
                });

                // Handle last group
                if (currentContractId !== null && rowCount > 1) {
                    mergeColumns.forEach(function(colIndex) {
                        var cell = $(rows[startRow]).find('td').eq(colIndex);
                        if (cell.length > 0) {
                            cell.attr('rowspan', rowCount);
                            for (var i = 1; i < rowCount; i++) {
                                $(rows[startRow + i]).find('td').eq(colIndex).hide();
                            }
                        }
                    });
                }
            }
        });

        $(document).on('click', '.view_related_sales_modal', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            
            // Load into the existing modal container
            $('.customer_contract_modal').load(url, function() {
                $(this).modal('show');
            });
        });

        // View Modal Link
        $(document).on('click', '.view_customer_contract', function(e) {
            e.preventDefault();
            var url = $(this).data('href');
            $('.customer_contract_modal').load(url, function() {
                $(this).modal('show');
            });
        });
    });

    // --- CUSTOM EXPORT FUNCTION ---
    function exportContractWithMerges(dt, type) {
        var data = dt.rows({ search: 'applied' }).data().toArray();
        if (data.length === 0) {
            toastr.warning('No data to export');
            return;
        }

        var headers = ['Contact Name', 'Ref. No.', 'Contract Name', 'Period', 'Product Targets', 'Progress', 'Total Value', 'Status', 'Added By'];
        var processedData = [];
        var merges = []; 
        var startRowIndex = 1; 

        var currentContractId = null;
        var groupStartRow = null;
        var groupRowCount = 0;

        var stripHtml = function(html) {
            if (!html) return "";
            var tmp = document.createElement("DIV");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        };

        data.forEach(function(row, index) {
            var rowData = [
                row.contact_name,
                row.reference_no,
                row.contract_name,
                stripHtml(row.period),
                row.product_name, 
                stripHtml(row.progress),
                stripHtml(row.total_contract_value),
                stripHtml(row.status),
                row.added_by
            ];

            if (row.contract_id !== currentContractId) {
                if (currentContractId !== null && groupRowCount > 1) {
                    var colsToMerge = [0, 1, 2, 3, 6, 7, 8]; 
                    colsToMerge.forEach(function(colIdx) {
                        merges.push({ s: { r: groupStartRow, c: colIdx }, e: { r: groupStartRow + groupRowCount - 1, c: colIdx } });
                    });
                }
                currentContractId = row.contract_id;
                groupStartRow = startRowIndex + index;
                groupRowCount = 1;
                processedData.push(rowData);
            } else {
                groupRowCount++;
                var blankRow = [
                    '', '', '', '', 
                    row.product_name,        
                    stripHtml(row.progress), 
                    '', '', ''  
                ];
                processedData.push(blankRow);
            }
        });

        if (currentContractId !== null && groupRowCount > 1) {
            var colsToMerge = [0, 1, 2, 3, 6, 7, 8];
            colsToMerge.forEach(function(colIdx) {
                merges.push({ s: { r: groupStartRow, c: colIdx }, e: { r: groupStartRow + groupRowCount - 1, c: colIdx } });
            });
        }

        if (type === 'csv') {
            var csvContent = headers.join(",") + "\n";
            processedData.forEach(function(row) {
                var rowStr = row.map(function(field) {
                    return '"' + String(field).replace(/"/g, '""') + '"';
                }).join(",");
                csvContent += rowStr + "\n";
            });
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement("a");
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "All_Customer_Contracts.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            if (typeof XLSX === 'undefined') {
                alert('Excel library not loaded.');
                return;
            }
            var ws_data = [headers].concat(processedData);
            var ws = XLSX.utils.aoa_to_sheet(ws_data);
            ws['!merges'] = merges;
            ws['!cols'] = [{wch: 25}, {wch: 15}, {wch: 25}, {wch: 25}, {wch: 30}, {wch: 20}, {wch: 15}, {wch: 10}, {wch: 15}];
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Contracts");
            XLSX.writeFile(wb, "All_Customer_Contracts.xlsx");
        }
    }
</script>
@endsection