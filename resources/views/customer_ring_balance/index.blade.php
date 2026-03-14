@extends('layouts.app')

@section('title', __('Customer Ring Balance'))

@section('content')
<section class="content-header">
    <h1>{{ __('Customer Ring Balance') }}</h1>
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
                        <!-- Button to trigger the modal -->
                        <button class="btn btn-block btn-primary" onclick="window.location='{{ route('customer-ring-balance.create') }}'">
                                <i class="fa fa-plus"></i> @lang('messages.add')
                            </button>
                    </div>
                @endslot
                <table class="table table-bordered table-striped" id="ring_balance">
                    <thead>
                        <tr>
                            <th>@lang('messages.action')</th>
                            <th>Date</th>
                            <th>Invoice Sell No</th>
                            <th>Number</th>
                            <th>Contact Name</th>
                            <th>Contact Mobile</th>
                            <th>Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>
            @endcomponent
        </div>
    </div>
</section>
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
        table.ajax.reload(); // Reload table when dates are set
    });

    // Clear input when date range is cancelled
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        table.ajax.reload(); // Reload table when date range is cleared
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

    var table = $('#ring_balance').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url("customer-ring-balance") }}',
            data: function (d) {
                d.location_id = $('#sell_list_filter_location_id').val();
                d.contact_id = $('#sell_list_filter_contact_id').val();
                d.status = $('#sell_list_filter_status').val();

                // Only include the date filter if a date range has been selected
                if ($('#sell_list_filter_date_range').val() !== '') {
                    var dateRangePicker = $('#sell_list_filter_date_range').data('daterangepicker');
                    if (dateRangePicker) {
                        d.start_date = dateRangePicker.startDate.format('YYYY-MM-DD');
                        d.end_date = dateRangePicker.endDate.format('YYYY-MM-DD');
                    }
                }
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false},
            {data: 'date', name: 'date'},
            {data: 'sell_ref_invoice', name: 'sell_ref_invoice'},
            {data: 'invoice_no', name: 'invoice_no'},
            {data: 'contact_name', name: 'contact_name'},
            {data: 'contact_mobile', name: 'contact_mobile'},
            {data: 'location_name', name: 'location_name'},
            {
                data: 'status',
                name: 'status',
                render: function(data, type, row) {
                    if (data === 'pending') {
                        return '<span class="status-pending">Pending</span>';
                    } else if (data === 'completed') {
                        return '<span class="status-completed">Completed</span>';
                    }
                    return data; // Fallback for other statuses
                }
            }
        ]
    });

    // Reload table when filters change
    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_status').change(function() {
        table.ajax.reload(null, false); // Reload table without resetting pagination
    });

   $(document).on('click', '.delete-ring-balance', function(e) {
        e.preventDefault();

        var url = $(this).data('href');
        var csrfToken = $(this).data('csrf');

        // Show custom confirmation dialog like in your sample
        swal({
            title: "Are you sure ?",
            text: "This will delete the top-up transaction and reverse the ring balance.",
            icon: "warning",
            buttons: {
                cancel: {
                    text: "Cancel",
                    value: null,
                    visible: true,
                    className: "btn-secondary",
                    closeModal: true,
                },
                confirm: {
                    text: "OK",
                    value: true,
                    visible: true,
                    className: "btn-danger",
                    closeModal: true
                }
            },
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        "_token": csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            swal("Deleted!", response.message, "success");
                            table.ajax.reload(); // Reload the DataTable
                        } else {
                            swal("Failed!", response.message || 'Error occurred while deleting', "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        swal("Error", "Failed to delete transaction.", "error");
                    }
                });
            }
        });
    });
});
</script>
@endsection
