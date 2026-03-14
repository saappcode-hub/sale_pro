@extends('layouts.app')

@section('title', __('Warehouse Scan'))

@section('content')
<section class="content-header">
    <h1>{{ __('Warehouse Scan') }}</h1>
</section>
<style>
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
</style>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_user_id', __('report.user') . ':') !!}
                {!! Form::select('sell_list_filter_user_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_status', __('Status') . ':') !!}
                {!! Form::select('sell_list_filter_status', ['' => __('All'), 'partial' => __('Partial'), 'completed' => __('Completed')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
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
                <table class="table table-bordered table-striped" id="sales_order_scan_table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>@lang('messages.action')</th>
                            <th>Scan Date</th>
                            <th>Referece No</th>
                            <th>Invoice No.</th>
                            <th>Updated At</th>
                            <th>Created By</th>
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
    var table = $('#sales_order_scan_table').DataTable({
        processing: true,
        serverSide: true,
        scrollY: "75vh",
        scrollX: true,
        scrollCollapse: true,
        ajax: {
            url: '{{ url("sale-order-scan") }}',
            data: function (d) {
                d.user_id = $('#sell_list_filter_user_id').val();
                d.status = $('#sell_list_filter_status').val();

                // Check if daterangepicker is initialized and send start_date and end_date
                if($('#sell_list_filter_date_range').val()) {
                    var start = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }
            }
        },
        columns: [
            {data: 'action', name: 'action', orderable: false, searchable: false},
            {data: 'created_at', name: 'created_at'},
            {data: 'ref_no', name: 'ref_no'},
            {data: 'sale_ref', name: 'sale_ref'}, 
            {data: 'updated_at', name: 'updated_at'}, 
            {data: 'username', name: 'username'},
            {
                data: 'status', 
                name: 'status',
                render: function(data, type, row) {
                    if (data === 'completed') {
                        return '<span class="status-completed">Completed</span>';
                    } else if (data === 'partial') {
                        return '<span class="status-partial">Partial</span>';
                    }
                    return data;
                }
            }
        ]
    });

    // Handle filter changes and reload the table
    $('#sell_list_filter_user_id, #sell_list_filter_status').change(function() {
        table.ajax.reload(null, false); 
    });

    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            $('#sales_order_scan_table').DataTable().ajax.reload(); // Reload the table
        }
    );
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        $('#sales_order_scan_table').DataTable().ajax.reload(); // Reload the table
    });

});
</script>
@endsection