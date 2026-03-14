@extends('layouts.app')

@section('title', __('Customer Ring'))

@section('content')
<section class="content-header">
    <h1>{{ __('Customer Ring') }}</h1>
</section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'business_location_filter']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_contact_id', __('Customer') . ':') !!}
                {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'contact_filter']) !!}
            </div>
        </div>
    </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <table class="table table-bordered table-striped" id="customer_ring">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Contact ID</th>
                            <th>Business Location</th>
                            <th>Contact Name</th>
                            <th>Contact Mobile</th>
                            <th>Total Ring Balance</th>
                            <th>Total Cash Ring($)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            @endcomponent
        </div>
    </div>
</section>

{{-- ===================== ADJUST STOCK MODAL ===================== --}}
<div class="modal fade" id="adjustStockModal" tabindex="-1" role="dialog" aria-labelledby="adjustStockModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="adjustStockModalLabel">
                    <i class="fa fa-edit"></i> Adjust Stock
                </h4>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer:</label>
                            <input type="text" id="adj_customer_name" class="form-control" readonly>
                            <input type="hidden" id="adj_contact_id">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Adjustment Date &amp; Time:</label>
                            <input type="datetime-local" id="adj_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fa fa-search text-info"></i> Search Product to Add to List:</label>
                    <input type="text" id="adj_product_search" class="form-control"
                           placeholder="Type product name (e.g. Coca, Beer)...">
                    <ul id="adj_product_suggestions" class="list-group" style="position:absolute;z-index:9999;width:96%;display:none;"></ul>
                </div>

                <table class="table table-bordered table-striped" id="adj_products_table">
                    <thead>
                        <tr>
                            <th>Product / Balance Name</th>
                            <th style="width:200px;">Type</th>
                            <th style="width:160px;">Quantity</th>
                            <th style="width:50px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="adj_products_body">
                        <tr id="adj_no_products_row">
                            <td colspan="4" class="text-center text-muted">No products added yet. Search above to add.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="form-group">
                    <label>Note:</label>
                    <textarea id="adj_note" class="form-control" rows="3" placeholder="Reason for adjustment..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="adj_save_btn">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>

        </div>
    </div>
</div>
{{-- ============================================================== --}}
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {

    // ── DataTable ────────────────────────────────────────────────
    var table = $('#customer_ring').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('customer-ring.index') }}",
            type: "GET",
            data: function(d) {
                d.business_location_id = $('#business_location_filter').val();
                d.contact_id = $('#contact_filter').val();
            }
        },
        columns: [
            { data: 'action', name: 'action', searchable: false, orderable: false },
            { data: 'contact_id', name: 'contact_id' },
            { data: 'business_name', name: 'business_name' },
            { data: 'contact_name', name: 'contact_name' },
            { data: 'contact_mobile', name: 'contact_mobile' },
            { data: 'total_ring_balance', name: 'total_ring_balance' },
            { data: 'total_cash_ring_balance', name: 'total_cash_ring_balance' }
        ]
    });

    $('#business_location_filter, #contact_filter').change(function() {
        table.ajax.reload();
    });

    // ── Open Adjust Stock Modal ──────────────────────────────────
    $(document).on('click', '.btn-adjust-stock', function() {
        var contactId   = $(this).data('contact-id');
        var contactName = $(this).data('contact-name');

        // Reset modal state
        $('#adj_contact_id').val(contactId);
        $('#adj_customer_name').val(contactName);
        $('#adj_note').val('');
        $('#adj_products_body').html(
            '<tr id="adj_no_products_row"><td colspan="4" class="text-center text-muted">No products added yet. Search above to add.</td></tr>'
        );
        $('#adj_product_search').val('');
        $('#adj_product_suggestions').hide().empty();

        // Set default datetime to now
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        $('#adj_date').val(now.toISOString().slice(0,16));

        // Pre-load existing products with non-zero balance for this contact
        loadExistingProducts(contactId);

        $('#adjustStockModal').modal('show');
    });

    // ── Load existing products from server ───────────────────────
    function loadExistingProducts(contactId) {
        $.ajax({
            url: "{{ route('customer-ring.adjust-stock-products') }}",
            type: 'GET',
            data: { contact_id: contactId },
            success: function(response) {
                if (response.products && response.products.length > 0) {
                    $('#adj_no_products_row').remove();
                    $.each(response.products, function(i, p) {
                        addProductRow(p.product_id, p.product_name, p.stock_ring_balance);
                    });
                }
            }
        });
    }

    // ── Product Search Autocomplete ──────────────────────────────
    var searchTimer;
    $('#adj_product_search').on('input', function() {
        clearTimeout(searchTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#adj_product_suggestions').hide().empty(); return; }

        searchTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('customer-ring.search-products') }}",
                type: 'GET',
                data: { q: q, contact_id: $('#adj_contact_id').val() },
                success: function(res) {
                    var $ul = $('#adj_product_suggestions').empty();
                    if (res.length === 0) {
                        $ul.append('<li class="list-group-item text-muted">No products found.</li>').show();
                        return;
                    }
                    $.each(res, function(i, p) {
                        $ul.append(
                            $('<li class="list-group-item list-group-item-action" style="cursor:pointer;">')
                                .text(p.product_name + ' (Current: ' + p.stock_ring_balance + ')')
                                .data('product', p)
                        );
                    });
                    $ul.show();
                }
            });
        }, 300);
    });

    $(document).on('click', '#adj_product_suggestions li', function() {
        var p = $(this).data('product');
        if (!p) return;

        // Avoid duplicate rows
        if ($('#adj_products_body').find('tr[data-product-id="' + p.product_id + '"]').length > 0) {
            toastr.warning('Product already in list.');
            return;
        }

        $('#adj_no_products_row').remove();
        addProductRow(p.product_id, p.product_name, p.stock_ring_balance);
        $('#adj_product_search').val('');
        $('#adj_product_suggestions').hide().empty();
    });

    // Hide suggestions on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#adj_product_search, #adj_product_suggestions').length) {
            $('#adj_product_suggestions').hide();
        }
    });

    // ── Add product row helper ───────────────────────────────────
    function addProductRow(productId, productName, currentBalance) {
        var row = '<tr data-product-id="' + productId + '">' +
            '<td>' +
                '<strong>' + productName + '</strong><br>' +
                '<small class="text-muted">Current: ' + currentBalance + '</small>' +
            '</td>' +
            '<td>' +
                '<select class="form-control adj-type">' +
                    '<option value="add">Add (+)</option>' +
                    '<option value="subtract">Subtract (-)</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="number" class="form-control adj-quantity" min="0" step="any" value="0">' +
            '</td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-danger btn-xs btn-remove-adj-row">' +
                    '<i class="fa fa-trash"></i>' +
                '</button>' +
            '</td>' +
        '</tr>';
        $('#adj_products_body').append(row);
    }

    // ── Remove row ───────────────────────────────────────────────
    $(document).on('click', '.btn-remove-adj-row', function() {
        $(this).closest('tr').remove();
        if ($('#adj_products_body tr').length === 0) {
            $('#adj_products_body').append(
                '<tr id="adj_no_products_row"><td colspan="4" class="text-center text-muted">No products added yet. Search above to add.</td></tr>'
            );
        }
    });

    // ── Save Adjustment ──────────────────────────────────────────
    $('#adj_save_btn').on('click', function() {
        var contactId = $('#adj_contact_id').val();
        var adjDate   = $('#adj_date').val();
        var note      = $('#adj_note').val();
        var products  = [];

        $('#adj_products_body tr[data-product-id]').each(function() {
            var productId = $(this).data('product-id');
            var type      = $(this).find('.adj-type').val();
            var qty       = parseFloat($(this).find('.adj-quantity').val()) || 0;
            if (qty !== 0) {
                products.push({ product_id: productId, type: type, quantity: qty });
            }
        });

        if (!contactId) { toastr.error('No customer selected.'); return; }
        if (products.length === 0) {
            toastr.error('Please add at least one product with a non-zero quantity.');
            return;
        }

        $('#adj_save_btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: "{{ route('customer-ring.adjust-stock') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                contact_id: contactId,
                adjustment_date: adjDate,
                note: note,
                products: products
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Adjustment saved successfully.');
                    $('#adjustStockModal').modal('hide');
                    table.ajax.reload();
                } else {
                    toastr.error(res.message || 'Failed to save adjustment.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'An error occurred. Please try again.';
                toastr.error(msg);
            },
            complete: function() {
                $('#adj_save_btn').prop('disabled', false).html('<i class="fa fa-save"></i> Save');
            }
        });
    });

});
</script>
@endsection