@extends('layouts.app')

@section('title', __('Supplier Exchange(Ring Cash)'))

@section('content')
<section class="content-header">
    <h1>{{ __('Supplier Exchange(Ring Cash)') }}</h1>
</section>
<style>
    .status-pending {
        background-color: orange; /* Color for Pending */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-completed {
        background-color: #20c997; /* Color for Completed */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-send {
        background-color: #007bff; /* Blue for Send */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-claim {
        background-color: #20c997; /* Blue for Claim */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
</style>

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
                {!! Form::label('sell_list_filter_contact_id', __('Supplier') . ':') !!}
                {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_status', __('Status') . ':') !!}
                {!! Form::select('sell_list_filter_status', ['' => __('All'), 'pending' => __('Pending'), 'send' => __('Send'), 'claim' => __('Claim')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                        <!-- Button to trigger the modal -->
                        <button class="btn btn-block btn-primary" onclick="window.location='{{ route('supplier-cash-ring-balance.create') }}'">
                                <i class="fa fa-plus"></i> @lang('messages.add')
                            </button>
                    </div>
                @endslot
                <table class="table table-bordered table-striped" id="supplier_cash_ring_balance">
                    <thead>
                        <tr>
                            <th>@lang('messages.action')</th>
                            <th>Date</th>
                            <th>Reference No</th>
                            <th>Supplier Name</th>
                            <th>Supplier Mobile</th>
                            <th>Location</th>
                            <th>Total Amount(៛)</th>
                            <th>Total Amount($)</th>
                            <th>Ring Status</th>
                            <th>Added By</th>
                        </tr>
                    </thead>
                </table>
            @endcomponent
        </div>
    </div>
</section>

<!-- View Modal -->
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Initialize date range picker
    $('#sell_list_filter_date_range').daterangepicker({
        locale: {
            format: 'YYYY-MM-DD' // Specify the format
        },
        autoUpdateInput: false // Ensure the input is not automatically updated
    });

    // Update input value only when a date range is selected
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
        supplier_cash_ring_balance_table.ajax.reload();
    });

    // Clear input when date range is cancelled
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        supplier_cash_ring_balance_table.ajax.reload();
    });

    // Initialize select2 for contact with custom template
    $('#sell_list_filter_contact_id').select2({
        templateResult: formatContact,
        templateSelection: formatContactSelection,
        width: '100%'
    });

    function formatContact(contact) {
        if (!contact.id) return contact.text; // Handle the "All" option
        var $contact = $(
            '<div>' + contact.text.split('<br>').join('</div><div>') + '</div>'
        );
        return $contact;
    }

    function formatContactSelection(contact) {
        if (!contact.id) return contact.text; // Handle the "All" option
        // Extract only the name (before the "(contact_id)")
        return contact.text.split(' (')[0].trim();
    }

    // Initialize DataTable
    var supplier_cash_ring_balance_table = $('#supplier_cash_ring_balance').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("supplier-cash-ring-balance.index") }}',
            data: function (d) {
                d.location_id = $('#sell_list_filter_location_id').val();
                d.contact_id = $('#sell_list_filter_contact_id').val();
                d.status = $('#sell_list_filter_status').val();
                d.date_range = $('#sell_list_filter_date_range').val();
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false},
            {data: 'date', name: 'transaction_date'},
            {data: 'reference_no', name: 'invoice_no'},
            {data: 'supplier_name', name: 'supplier_name'},
            {data: 'supplier_mobile', name: 'supplier_mobile'},
            {data: 'location_name', name: 'location_name'},
            {data: 'total_amount_riel', name: 'total_amount_riel'},
            {data: 'total_amount_dollar', name: 'total_amount_dollar'},
            {data: 'status', name: 'status'},
            {data: 'added_by', name: 'added_by'}
        ],
        order: [[1, 'desc']],
        "fnDrawCallback": function (oSettings) {
            __currency_convert_recursively($('#supplier_cash_ring_balance'));
        }
    });

    // Reload table when filters change
    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_status').change(function() {
        supplier_cash_ring_balance_table.ajax.reload();
    });

    // Handle modal for view
    $(document).on('click', '.btn-modal', function(e) {
        e.preventDefault();
        var container = $(this).data('container');
        $.get($(this).data('href'), function(data) {
            $(container).html(data).modal('show');
        });
    });
});
</script>
@endsection