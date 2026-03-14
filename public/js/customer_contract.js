let customerContractTable;
let productRowIndex = 0;

// Initialize DataTable
function initializeCustomerContractDataTable() {
    var table = $('#customer_contract_table');
    var url = table.data('url');

    if (!url) return;

    if (typeof XLSX === 'undefined') {
        console.warn('SheetJS (XLSX) library not loaded. Excel export might not work.');
    }

    customerContractTable = table.DataTable({
        processing: true,
        serverSide: true,
        destroy: true,
        ajax: {
            url: url,
            error: function (xhr, error, code) {
                console.log(xhr);
            }
        },
        dom: '<"row"<"col-md-3"l><"col-md-6 text-center"B><"col-md-3"f>>rtip',
        
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fa fa-file-text-o" aria-hidden="true"></i> ' + LANG.export_to_csv,
                className: 'btn-default btn-sm', 
                action: function (e, dt, node, config) {
                    exportContractWithMerges(dt, 'csv');
                }
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel-o" aria-hidden="true"></i> ' + LANG.export_to_excel,
                className: 'btn-default btn-sm', 
                action: function (e, dt, node, config) {
                    exportContractWithMerges(dt, 'excel');
                }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print" aria-hidden="true"></i> ' + LANG.print,
                className: 'btn-default btn-sm', 
                exportOptions: {
                    columns: ':visible:not(:eq(0))', 
                    stripHtml: true
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-columns" aria-hidden="true"></i> ' + LANG.col_vis,
                className: 'btn-default btn-sm' 
            },
            {
                extend: 'pdf',
                text: '<i class="fa fa-file-pdf-o" aria-hidden="true"></i> ' + LANG.export_to_pdf,
                className: 'btn-default btn-sm', 
                exportOptions: {
                    columns: ':visible:not(:eq(0))' 
                }
            }
        ],
        columns: [
            // 0. Action
            { data: 'action', name: 'action', searchable: false, orderable: false, width: '5%', className: 'text-center vertical-align-middle' },
            // 1. Ref
            { data: 'reference_no', name: 'customer_contracts.reference_no', className: 'vertical-align-middle' },
            // 2. Name
            { data: 'contract_name', name: 'customer_contracts.contract_name', className: 'vertical-align-middle' },
            // 3. Period
            { data: 'period', name: 'period', searchable: false, className: 'text-center vertical-align-middle' }, 
            
            // 4. Product Targets
            { 
                data: 'product_name', 
                name: 'products.name', 
                className: 'vertical-align-middle',
                render: function(data, type, row) {
                    return data; 
                }
            },

            // 5. Progress
            { data: 'progress', name: 'progress', searchable: false, orderable: false, className: 'text-center vertical-align-middle' },
            // 6. Value
            { data: 'total_contract_value', name: 'customer_contracts.total_contract_value', className: 'text-center vertical-align-middle' },
            // 7. Status
            { data: 'status', name: 'status', searchable: false, className: 'text-center vertical-align-middle' },
            // 8. Added By
            { data: 'added_by', name: 'users.first_name', className: 'vertical-align-middle' } 
        ],
        // Merging Logic (Visual)
        drawCallback: function(settings) {
            var api = this.api();
            var rows = api.rows({ page: 'current' }).nodes();
            var data = api.rows({ page: 'current' }).data();

            // Merge: Action(0), Ref(1), Name(2), Period(3), Value(6), Status(7), AddedBy(8)
            // WE SKIP COLUMN 4 (Product) AND COLUMN 5 (Progress) so they remain unique per row
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
}

// --- CUSTOM EXPORT FUNCTION ---
function exportContractWithMerges(dt, type) {
    var data = dt.rows({ search: 'applied' }).data().toArray();
    
    if (data.length === 0) {
        toastr.warning('No data to export');
        return;
    }

    // Headers (No Action)
    var headers = ['Ref. No.', 'Contract Name', 'Period', 'Product Targets', 'Progress', 'Total Value', 'Status', 'Added By'];
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
            row.reference_no,
            row.contract_name,
            stripHtml(row.period),
            row.product_name, 
            stripHtml(row.progress), // Progress is here for the start row
            stripHtml(row.total_contract_value),
            stripHtml(row.status),
            row.added_by
        ];

        if (row.contract_id !== currentContractId) {
            if (currentContractId !== null && groupRowCount > 1) {
                // Merge Cols: Ref(0), Name(1), Period(2), Value(5), Status(6), Added(7)
                // SKIP Product(3) and Progress(4)
                var colsToMerge = [0, 1, 2, 5, 6, 7]; 
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
            // For subsequent rows in the group:
            // Fill Product (Index 3) AND Progress (Index 4)
            // Leave others blank for merging
            var blankRow = [
                '', // Ref
                '', // Name
                '', // Period
                row.product_name,        // Product Target
                stripHtml(row.progress), // Progress (Unique per row)
                '', // Value
                '', // Status
                ''  // Added By
            ];
            processedData.push(blankRow);
        }
    });

    if (currentContractId !== null && groupRowCount > 1) {
        // Merge Cols: Ref(0), Name(1), Period(2), Value(5), Status(6), Added(7)
        var colsToMerge = [0, 1, 2, 5, 6, 7];
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
        link.setAttribute("download", "Customer_Contracts.csv");
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
        // Adjusted widths: Ref, Name, Period, Product, Progress, Value, Status, Added
        ws['!cols'] = [{wch: 15}, {wch: 25}, {wch: 25}, {wch: 30}, {wch: 20}, {wch: 15}, {wch: 10}, {wch: 15}];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Contracts");
        XLSX.writeFile(wb, "Customer_Contracts.xlsx");
    }
}

// Load form (Add/Edit)
$(document).on('click', '.customer_contract_btn', function(e) {
    e.preventDefault();
    let href = $(this).data('href');

    $.ajax({
        url: href,
        dataType: 'html',
        success: function(result) {
            $('.customer_contract_modal').html(result).modal('show');
            
            // Initialize date pickers
            $('#start_date').datetimepicker({ format: 'YYYY-MM-DD', ignoreReadonly: true });
            $('#end_date').datetimepicker({ format: 'YYYY-MM-DD', ignoreReadonly: true });

            // Initialize product search & dropzone
            setTimeout(function() {
                initializeProductSearch();
                initializeContractDropzone();
            }, 500);

            productRowIndex = $('#contract_products_tbody tr').length;
            calculateContractTotals();
        },
        error: function() {
            toastr.error('Error loading form');
        }
    });
});

// Dropzone Initialization
var contractDropzone = {};
function initializeContractDropzone() {
    if ($("div#contractUpload").length === 0) return;

    if (contractDropzone.length > 0 || Dropzone.instances.length > 0) {
        try { Dropzone.forElement("div#contractUpload").destroy(); } catch(e) {}
    }

    contractDropzone = $("div#contractUpload").dropzone({
        url: '/post-document-upload', 
        paramName: 'file',
        uploadMultiple: true,
        autoProcessQueue: true,
        addRemoveLinks: true,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(file, response) {
            if (response.success) {
                toastr.success(response.msg);
                // Append new file names to hidden input
                var existingFiles = $('input#contract_document_names').val();
                var newFiles = existingFiles ? existingFiles + ',' + response.file_name : response.file_name;
                $('input#contract_document_names').val(newFiles);
            } else {
                toastr.error(response.msg);
            }
        },
        removedfile: function(file) {
            if(file.previewElement) file.previewElement.remove();
        }
    });
}

// Delete Existing Media Handler
$(document).on('click', '.delete_contract_media', function(e) {
    e.preventDefault();
    var btn = $(this);
    var url = btn.data('href');
    var container = btn.closest('.media-item');

    swal({
        title: LANG.sure, 
        text: "This file will be deleted permanently.",
        icon: "warning",
        buttons: true,
        dangerMode: true,
    }).then((willDelete) => {
        if (willDelete) {
            $.ajax({
                method: 'DELETE',
                url: url,
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(result) {
                    if (result.success) {
                        toastr.success(result.msg);
                        container.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        toastr.error(result.msg);
                    }
                }
            });
        }
    });
});

// Product Search & Add Logic
function initializeProductSearch() {
    var searchInput = $('#search_contract_product');
    if (searchInput.length === 0) return;

    var searchUrl = searchInput.data('url');
    if (searchInput.data('autocomplete')) searchInput.autocomplete('destroy');

    searchInput.autocomplete({
        appendTo: ".customer_contract_modal", 
        delay: 300,
        minLength: 1,
        source: function(request, response) {
            $.ajax({
                url: searchUrl,
                type: 'GET',
                dataType: 'json',
                data: { term: request.term },
                success: function(data) { response(data); }
            });
        },
        select: function(event, ui) {
            event.preventDefault();
            addProductToContract(ui.item);
            $(this).val('');
        }
    }).autocomplete('instance')._renderItem = function(ul, item) {
        var string = '<div style="padding: 8px; border-bottom: 1px solid #eee; cursor:pointer;">';
        string += '<strong>' + (item.text || item.name) + '</strong>';
        string += '<span style="font-size: 12px; color: #666;"> SKU: ' + item.sub_sku + '</span>';
        if (item.selling_price > 0) string += ' | <span style="color: #27ae60;">$' + parseFloat(item.selling_price).toFixed(2) + '</span>';
        string += '</div>';
        return $('<li>').append(string).appendTo(ul);
    };
}

function addProductToContract(product) {
    if (!product.product_id) return;
    
    let exists = false;
    $('#contract_products_tbody tr').each(function() {
        let existingId = $(this).find('.product-id-input').val();
        let existingVarId = $(this).find('.variation-id-input').val();
        if (existingId == product.product_id && existingVarId == product.variation_id) {
            exists = true;
            let qtyInput = $(this).find('.quantity-input');
            let newQty = (parseFloat(qtyInput.val()) || 0) + 1;
            qtyInput.val(newQty).trigger('change'); 
            toastr.info('Product quantity updated');
            return false;
        }
    });

    if (!exists) {
        let sellingPrice = parseFloat(product.selling_price) || 0;
        let newRow = `
            <tr class="product-row">
                <td style="vertical-align: middle;">
                    <input type="hidden" name="products[${productRowIndex}][product_id]" value="${product.product_id}" class="product-id-input">
                    <input type="hidden" name="products[${productRowIndex}][variation_id]" value="${product.variation_id}" class="variation-id-input">
                    <div class="product-name-display">
                        <strong style="display: block;">${product.name}</strong>
                        <small class="text-muted">SKU: ${product.sub_sku}</small>
                    </div>
                </td>
                
                <td style="vertical-align: middle; text-align: center;">
                    <div class="input-group input-group-sm" style="width: 120px; margin: 0 auto;">
                        <span class="input-group-btn"><button type="button" class="btn btn-default btn-xs qty-minus"><i class="fa fa-minus"></i></button></span>
                        <input type="number" name="products[${productRowIndex}][target_quantity]" class="form-control quantity-input text-center" value="1" min="1">
                        <span class="input-group-btn"><button type="button" class="btn btn-default btn-xs qty-plus"><i class="fa fa-plus"></i></button></span>
                    </div>
                </td>

                <td style="vertical-align: middle; text-align: center;">
                    <input type="text" name="products[${productRowIndex}][unit_price]" class="form-control price-input text-center input-sm" value="${sellingPrice.toFixed(2)}">
                </td>

                <td style="vertical-align: middle; padding: 8px 2px; text-align: center;">
                    <div class="input-group input-group-sm">
                        <input type="text" name="products[${productRowIndex}][discount]" class="form-control discount-input text-center" value="0.00">
                        <span class="input-group-btn">
                            <select name="products[${productRowIndex}][discount_type]" class="form-control discount-type-select"><option value="Fixed">Fixed</option><option value="Percentage">%</option></select>
                        </span>
                    </div>
                </td>

                <td class="text-right" style="vertical-align: middle;">
                    <span class="subtotal" style="color: #d63384; font-weight: bold;">$${sellingPrice.toFixed(2)}</span>
                </td>
                <td class="text-center" style="vertical-align: middle;">
                    <button type="button" class="btn btn-link text-danger remove-product"><i class="fa fa-times"></i></button>
                </td>
            </tr>`;
        $('#contract_products_tbody').append(newRow);
        productRowIndex++;
        calculateContractTotals();
    }
}

// Calculations & Events
$(document).on('click', '.qty-plus', function() {
    let input = $(this).closest('.input-group').find('.quantity-input');
    input.val((parseFloat(input.val()) || 0) + 1).trigger('change'); 
});
$(document).on('click', '.qty-minus', function() {
    let input = $(this).closest('.input-group').find('.quantity-input');
    let val = parseFloat(input.val()) || 0;
    if (val > 1) input.val(val - 1).trigger('change');
});
$(document).on('click', '.remove-product', function() {
    $(this).closest('tr').remove();
    calculateContractTotals();
});
$(document).on('change keyup', '.quantity-input, .price-input, .discount-input, .discount-type-select', function() {
    let row = $(this).closest('tr');
    let quantity = parseFloat(row.find('.quantity-input').val()) || 0;
    let price = parseFloat(row.find('.price-input').val()) || 0;
    let discount = parseFloat(row.find('.discount-input').val()) || 0;
    let type = row.find('.discount-type-select').val();
    
    let lineTotal = quantity * price;
    let discountAmount = (type === 'Percentage' || type === '%') ? (lineTotal * discount / 100) : discount;
    
    row.find('.subtotal').text('$' + (lineTotal - discountAmount).toFixed(2));
    calculateContractTotals();
});

function calculateContractTotals() {
    let totalUnits = 0;
    let totalValue = 0;
    $('#contract_products_tbody tr').each(function() {
        let quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
        let subtotal = parseFloat($(this).find('.subtotal').text().replace('$', '')) || 0;
        totalUnits += quantity;
        totalValue += subtotal;
    });
    $('#total_units').text(totalUnits);
    $('#total_value').text('$' + totalValue.toFixed(2));
}

// Form Submission
$(document).on('submit', 'form#customer_contract_form', function(e) {
    e.preventDefault();
    let form = $(this);
    if ($('#contract_products_tbody tr').length === 0) {
        toastr.error('Please add at least one product');
        return;
    }
    $.ajax({
        type: form.attr('method'),
        url: form.attr('action'),
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('.customer_contract_modal').modal('hide');
                toastr.success(response.msg);
                if (typeof customerContractTable !== 'undefined') customerContractTable.ajax.reload();
            } else {
                toastr.error(response.msg);
            }
        },
        error: function() { toastr.error('Error saving contract'); }
    });
});

// Delete Contract Handler
$(document).on('click', '.delete_customer_contract', function(e) {
    e.preventDefault();
    let href = $(this).data('href');
    swal({
        title: "Are you sure?",
        icon: 'warning',
        buttons: true,
        dangerMode: true,
    }).then(willDelete => {
        if (willDelete) {
            $.ajax({
                type: 'DELETE',
                url: href,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                        customerContractTable.ajax.reload();
                    } else {
                        toastr.error(response.msg);
                    }
                }
            });
        }
    });
});

$(document).on('click', '.view_customer_contract', function(e) {
    e.preventDefault();
    // Get the ID from the data attribute we set in the controller (see Step 4)
    let contractId = $(this).data('contract-id');
    
    // Construct URL (assuming route pattern matches existing pattern)
    // Or you can put the full URL in data-href attribute
    let href = $(this).data('href'); 
    
    // If using the ID approach:
    if (!href) {
        href = '/contacts/contract-view/' + contractId;
    }

    $.ajax({
        url: href,
        dataType: 'html',
        success: function(result) {
            $('.customer_contract_modal').html(result).modal('show');
        },
        error: function() {
            toastr.error('Error loading contract details');
        }
    });
});