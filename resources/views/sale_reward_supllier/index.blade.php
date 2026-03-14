@extends('layouts.app')

@section('title', __('Supplier Reward Exchange'))

@section('content')
<section class="content-header">
    <h1>{{ __('Supplier Reward Exchange') }}</h1>
</section>
<style>
    .status-pending {
        background-color: orange;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-completed {
        background-color: #20c997;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .status-partial {
        background-color: #328AC9;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        text-align: center;
    }
    .modal-content {
    border-radius: 10px;
    padding: 20px;
    }

    .modal-body h5 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .modal-body p {
        font-size: 14px;
        color: #6c757d;
    }

    .modal-footer .btn {
        width: 120px;
        padding: 10px 20px;
        font-size: 16px;
    }

    .modal-footer .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }

</style>
<meta name="csrf-token" content="{{ csrf_token() }}">

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
                {!! Form::select('sell_list_filter_status', ['' => __('All'), 'pending' => __('Pending'), 'partial' => __('Partial'), 'completed' => __('Completed')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                        <button class="btn btn-block btn-primary" id="openAddRewardModal" onclick="window.location='{{ route('sale-reward-supplier.create') }}'">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </button>
                    </div>
                @endslot
                <table class="table table-bordered table-striped" id="sale_order_reward">
                    <thead>
                        <tr>
                            <th>@lang('messages.action')</th>
                            <th>Date</th>
                            <th>Reference No</th> 
                            <th>Supplier Name</th>
                            <th>Supplier Mobile</th>
                            <th>Location</th>
                            <th>Ring Status</th> 
                            <th>Total Amount</th> 
                            <th>Added By</th> 
                        </tr>
                    </thead>
                </table>
            @endcomponent
        </div>
    </div>
</section>
@endsection
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-center">
            <div class="modal-header border-0">
                <h4 class="modal-title w-100 text-warning">
                    <i class="fa fa-exclamation-circle"></i>
                </h4>
            </div>
            <div class="modal-body">
                <h5>Are you sure?</h5>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-light btn-lg" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-lg" id="confirmDeleteButton">OK</button>
            </div>
        </div>
    </div>
</div>

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Set the current date
    var currentDate = moment().format('YYYY-MM-DD');
    var defaultDateRange = currentDate + ' ~ ' + currentDate;

    // Display default date range without triggering backend filtering
    $('#sell_list_filter_date_range').val(defaultDateRange);

    // Initialize the date range picker
    $('#sell_list_filter_date_range').daterangepicker({
        autoUpdateInput: false,
        locale: {
            format: 'YYYY-MM-DD'
        }
    });

    // Initialize DataTable without filtering by date initially
    var table = $('#sale_order_reward').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url("sale-reward-supplier") }}',
            data: function (d) {
                d.location_id = $('#sell_list_filter_location_id').val();
                d.contact_id = $('#sell_list_filter_contact_id').val();
                d.status = $('#sell_list_filter_status').val();

                // Only include date filter if the user has changed it
                if ($('#sell_list_filter_date_range').data('changed')) {
                    var dates = $('#sell_list_filter_date_range').val().split(' ~ ');
                    d.start_date = dates[0];
                    d.end_date = dates[1];
                }
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false},
            {data: 'date', name: 'date'},
            {data: 'ref_no', name: 'ref_no'},
            {data: 'contact_name', name: 'contact_name'}, 
            {data: 'contact_mobile', name: 'contact_mobile'}, 
            {data: 'location_name', name: 'location_name'},
            {
                data: 'sub_type', 
                name: 'sub_type',
                render: function(data, type, row) {
                    if (data === 'pending') {
                        return '<span class="status-pending">Pending</span>';
                    } else if (data === 'send') {
                        return '<span class="status-completed">Send</span>';
                    } 
                    return data;
                }
            },
            {data: 'final_total', name: 'final_total'},
            {data: 'added_by', name: 'added_by'}
        ]
    });

    // Track when the user changes the date range
    $('#sell_list_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).data('changed', true); // Mark date range as changed
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
        table.ajax.reload();
    });

    // Handle cancel action to reset date range
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val(defaultDateRange).data('changed', false); // Reset to default and mark as unchanged
        table.ajax.reload();
    });

    // Reload table on other filter changes
    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_status').change(function() {
        table.ajax.reload();
    });

    // Handle delete with SweetAlert confirmation
    $(document).on('click', '.delete-reward-exchange', function (e) {
        e.preventDefault();

        var url = $(this).data('href');
        var csrfToken = '{{ csrf_token() }}';

        swal({
            title: "Are you sure?",
            text: "This will delete the reward exchange.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        "_token": csrfToken
                    },
                    success: function (result) {
                        if (result.success) {
                            swal("Deleted!", result.message, "success");
                            table.ajax.reload();
                        } else {
                            swal("Failed!", result.message, "error");
                        }
                    },
                    error: function () {
                        swal("Error", "Failed to delete reward exchange.", "error");
                    }
                });
            }
        });
    });

});
</script>
@endsection