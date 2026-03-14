@extends('layouts.app')

@section('title', __('Product Sale Visit Setting'))

@section('content')
<section class="content-header">
    <h1>{{ __('Product Sale Visit Setting') }}</h1>
</section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('product_sale_visit_filter', 'Product Sale Visit' . ':') !!}
                {!! Form::select('product_sale_visit_filter', 
                    [
                        '' => __('All Products'),
                        '1' => __('Product For Sale Visit'),
                        '0' => __('Product Not For Sale Visit')
                    ], 
                    null, 
                    ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_sale_visit_filter']
                ) !!}
            </div>
        </div>
    </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="tab-content" style="padding: 20px 24px;">
                    <div class="tab-pane active" id="product_sale_visit_tab">
                        <table class="table table-bordered table-striped" id="product_sale_visit_table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-checkbox"></th>
                                    <th>Product Name</th>
                                    <th>SKU</th>
                                    <th>Product Sale Visit</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4">
                                        <div style="display: flex; width: 100%;">
                                            <button type="button" class="btn btn-xs btn-info update_product_sale_visit" data-type="set_sale_visit">Product Sale Visit</button>
                                            &nbsp;
                                            <button type="button" class="btn btn-xs btn-warning update_product_sale_visit" data-type="remove_sale_visit">Remove Product Sale Visit</button>
                                        </div>
                                    </td>
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
<script type="text/javascript">
$(document).ready(function() {
    var productSaleVisitTable;

    // Initialize DataTable
    function initProductSaleVisitTable() {
        if (productSaleVisitTable) {
            productSaleVisitTable.destroy();
        }
        
        productSaleVisitTable = $('#product_sale_visit_table').DataTable({
            processing: true,
            serverSide: true,
            order: [[2, 'desc']],// Order by product name descending
            ajax: {
                url: '{{ route("product_sale_visit.index") }}',
                data: function (d) {
                    d.product_sale_visit_filter = $('#product_sale_visit_filter').val();
                }
            },
            columns: [
            { 
                data: 'mass_select', 
                name: 'mass_select',
                searchable: false,
                orderable: false,
                width: '50px'
            },
            { 
                data: 'product_name', 
                name: 'products.name',  // This enables server-side search
                searchable: true 
            },
            { 
                data: 'sku', 
                name: 'products.sku',   // This enables server-side search
                searchable: true 
            },
            { 
                data: 'product_sale_visit', 
                name: 'products.product_sale_visit',
                searchable: false,
                orderable: true
            }
        ],
            pageLength: 25,
            responsive: true,
            createdRow: function(row, data, dataIndex) {
                $(row).find('td:eq(0)').attr('class', 'selectable_td');
            }
        });
    }

    // Initialize table on page load
    initProductSaleVisitTable();

    // Handle filter change
    $('#product_sale_visit_filter').on('change', function() {
        if (productSaleVisitTable) {
            productSaleVisitTable.ajax.reload();
            $('#select-all-checkbox').prop('checked', false);
        }
    });

    // Handle select all checkbox
    $('#select-all-checkbox').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('#product_sale_visit_table tbody input[type="checkbox"]').prop('checked', isChecked);
    });

    // Handle individual checkbox change
    $(document).on('change', '#product_sale_visit_table tbody input[type="checkbox"]', function() {
        var totalCheckboxes = $('#product_sale_visit_table tbody input[type="checkbox"]').length;
        var checkedCheckboxes = $('#product_sale_visit_table tbody input[type="checkbox"]:checked').length;
        $('#select-all-checkbox').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Get selected product IDs
    function getSelectedRows() {
        var selectedRows = [];
        $('#product_sale_visit_table tbody input[type="checkbox"]:checked').each(function() {
            selectedRows.push($(this).val());
        });
        return selectedRows;
    }

    // Handle Product Sale Visit buttons using SweetAlert (same as product list)
    $(document).on('click', '.update_product_sale_visit', function(e){
        e.preventDefault();
        var selected_rows = getSelectedRows();
        var type = $(this).data('type');
        var sale_visit_value = (type === 'set_sale_visit') ? 1 : null; // Changed to null
        var action_text = (type === 'set_sale_visit') ? 'set Product Sale Visit' : 'remove Product Sale Visit';
        
        if(selected_rows.length > 0){
            swal({
                title: "Are you sure?",
                text: "This will " + action_text + " for selected products",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then((willUpdate) => {
                if (willUpdate) {
                    $.ajax({
                        method: "POST",
                        url: '{{ route("product_sale_visit.update") }}',
                        dataType: "json",
                        data: {
                            selected_products: selected_rows,
                            product_sale_visit: sale_visit_value,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(result){
                            if(result.success == true){
                                toastr.success(result.msg);
                                productSaleVisitTable.ajax.reload();
                                $('#select-all-checkbox').prop('checked', false);
                            } else {
                                toastr.error(result.msg);
                            }
                        },
                        error: function(xhr, status, error) {
                            toastr.error("An error occurred while updating Product Sale Visit");
                        }
                    });
                }
            });
        } else {
            swal('No row selected');
        }
    });
});
</script>
@endsection