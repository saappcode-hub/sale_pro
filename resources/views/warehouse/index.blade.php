@extends('layouts.app')

@section('title', __('purchase.purchases'))

@section('content')
<section class="content-header">
    <h1>{{ __('purchase.purchases') }}</h1>
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
    .status-ordered {
        background-color: #11cdef; /* Blue color for Ordered */
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .label-success {
        background-color: green;
        color: white;
        padding: 5px;
        border-radius: 3px;
    }
    .label-danger {
        background-color: red;
        color: white;
        padding: 5px;
        border-radius: 3px;
    }
    .label-warning {
        background-color: orange;
        color: white;
        padding: 5px;
        border-radius: 3px;
    }
    .label-partial {
        background-color: #11cdef; /* Color for Partial */
        color: white;
        padding: 5px;
        border-radius: 3px;
    }
</style>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_location_id',  __('purchase.business_location') . ':') !!}
                {!! Form::select('purchase_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_supplier_id',  __('purchase.supplier') . ':') !!}
                {!! Form::select('purchase_list_filter_supplier_id', $suppliers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_status',  __('purchase.purchase_status') . ':') !!}
                {!! Form::select('purchase_list_filter_status', ['received' => __('Received'), 'pending' => __('Pending'), 'ordered' => __('Ordered')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_payment_status',  __('purchase.payment_status') . ':') !!}
                {!! Form::select('purchase_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('purchase_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('purchase_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
    @endcomponent
    
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <table class="table table-bordered table-striped" id="purchase_tables">
                    <thead>
                        <tr>
                            <th>@lang('messages.action')</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('purchase.location')</th>
                            <th>@lang('purchase.supplier')</th>
                            <th>@lang('purchase.purchase_status')</th>
                            <th>@lang('purchase.payment_status')</th>
                            <th>@lang('lang_v1.added_by')</th>
                        </tr>
                    </thead>
                </table>
            @endcomponent
        </div>
    </div>
</section>
@endsection

<div class="modal fade" id="update_status_modal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="update_status_form" action="{{ route('warehouse.update_status', ':id') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">@lang('Update Status')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="purchase_status">@lang('Purchase Status:')</label>
                        <select id="purchase_status" name="status" class="form-control" required>
                            <option value="received">@lang('Received')</option>
                            <option value="pending">@lang('Pending')</option>
                            <option value="ordered">@lang('Ordered')</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('messages.close')</button>
                    <button type="submit" class="btn btn-primary">@lang('Update')</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Cache the base action URL
    var baseActionUrl = "{{ route('warehouse.update_status', ':id') }}";

    // Show the modal when "Update Status" is clicked
    $(document).on('click', '.update-status-btn', function(e) {
        e.preventDefault();

        var purchaseId = $(this).data('purchase-id');
        var currentStatus = $(this).data('current-status');

        // Set form values and show modal
        $('#update_status_modal').modal('show');
        $('#update_status_modal #purchase_status').val(currentStatus);

        // Disable options if current status is "received"
        var statusSelect = $('#update_status_modal #purchase_status');
        statusSelect.find('option').prop('disabled', false); // Reset all options
        
        if (currentStatus === 'received') {
            statusSelect.find('option[value="pending"]').prop('disabled', true);
            statusSelect.find('option[value="ordered"]').prop('disabled', true);
        }

        // Set the action URL with the correct ID
        var actionUrl = baseActionUrl.replace(':id', purchaseId);
        $('#update_status_form').attr('action', actionUrl);

        // Ensure form reset and action URL are correct when reopening the modal
        $('#update_status_form').off('submit'); // Remove previous submit event
        $('#update_status_form').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var url = $(this).attr('action');
            
            // Disable the submit button to prevent multiple clicks
            var submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true);

            $.ajax({
                method: 'PUT',
                url: url,
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Hide the modal
                        $('#update_status_modal').modal('hide');

                        // Reset the form
                        $('#update_status_form')[0].reset();

                        // Reset the form action URL to its base state
                        $('#update_status_form').attr('action', baseActionUrl);

                        // Reload the DataTable to reflect changes
                        $('#purchase_tables').DataTable().ajax.reload(null, false); // Reload without resetting pagination
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    // Handle validation errors
                    if (xhr.status === 422) {
                        var response = JSON.parse(xhr.responseText);
                        toastr.error(response.message);
                    } else {
                        toastr.error('An error occurred while updating the status.');
                    }
                },
                complete: function() {
                    // Re-enable the submit button after AJAX request
                    submitButton.prop('disabled', false);
                }
            });
        });
    });

    // Rest of your existing code...
    // Date range as a button
    $('#purchase_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#purchase_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            table.ajax.reload();
        }
    );
    $('#purchase_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#purchase_list_filter_date_range').val('');
        table.ajax.reload();
    });

    // Reload the table whenever filters are changed
    $('#purchase_list_filter_location_id, #purchase_list_filter_supplier_id, #purchase_list_filter_status, #purchase_list_filter_payment_status').change(function() {
        table.ajax.reload();
    });

    // Initialize DataTable
    var table = $('#purchase_tables').DataTable({
        processing: true,
        serverSide: true,
        order: [[1, 'desc']], // Sort by transaction_date column (index 1) in descending order
        ajax: {
            url: '{{ url("warehouse") }}',
            data: function (d) {
                // Get filter values from other filters
                d.location_id = $('#purchase_list_filter_location_id').val();
                d.supplier_id = $('#purchase_list_filter_supplier_id').val();
                d.status = $('#purchase_list_filter_status').val();
                d.payment_status = $('#purchase_list_filter_payment_status').val();

                // Only include the date filter if a date range has been selected
                if ($('#purchase_list_filter_date_range').val() !== '') {
                    var dateRangePicker = $('#purchase_list_filter_date_range').data('daterangepicker');
                    if (dateRangePicker) {
                        d.start_date = dateRangePicker.startDate.format('YYYY-MM-DD');
                        d.end_date = dateRangePicker.endDate.format('YYYY-MM-DD');
                    }
                }
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false},
            {data: 'transaction_date', name: 'transaction_date'},
            {data: 'ref_no', name: 'ref_no'},
            {data: 'location_name', name: 'location_name'},
            {data: 'name', name: 'name'},
            {data: 'status', name: 'status'},
            {data: 'payment_status', name: 'payment_status'},
            {data: 'added_by', name: 'added_by'},
        ],
        columnDefs: [
            {
                targets: [1],
                type: 'datetime', // Ensure the date column is sorted correctly
                render: function(data, type, row) {
                    if (type === 'display' || type === 'filter') {
                        return moment(data).format('YYYY-MM-DD HH:mm:ss'); // Format the date display if needed
                    }
                    return data;
                }
            }
        ]
    });
});
</script>
@endsection
