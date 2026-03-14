@extends('layouts.app')

@section('title', __('Warehouse Scan'))

@section('content')
<section class="content-header">
    <h1>{{ __('Stock Movement Report') }}</h1>
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
    .filter-section {
        background-color: #f4f4f4;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .apply-btn-container {
        display: flex;
        align-items: end;
        margin-top: 25px;
    }
</style>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
        <div class="col-md-4 apply-btn-container">
            <button type="button" id="apply_filters_btn" class="btn btn-primary">
                <i class="fa fa-search"></i> {{ __('Apply Filters') }}
            </button>
        </div>
    </div>
    @endcomponent
    
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <table class="table table-bordered table-striped" id="stock_movement_report" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Location</th>
                            <th>Beginning</th>
                            <th>Sale</th>
                            <th>Sale Return</th>
                            <th>Purchase</th>
                            <th>Purchase Return</th>
                            <th>Stock Transfers(In)</th>
                            <th>Stock Transfers(Out)</th>
                            <th>Stock Reward In</th>
                            <th>Stock Reward Out</th>
                            <th>Supplier Reward Receive</th>
                            <th>Supplier Reward Exchange</th>
                            <th>Adjustment</th>
                            <th>Ending Stock</th>
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
    var stock_movement_report;
    var filters_applied = false;
    
    // Initialize date range picker
    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        }
    );

    // Initialize DataTable with empty state
    stock_movement_report = $('#stock_movement_report').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('stock-movement-report.index') }}",
            data: function(d) {
                d.sell_list_filter_location_id = $('#sell_list_filter_location_id').val();
                d.sell_list_filter_date_range = $('#sell_list_filter_date_range').val();
                d.apply_filters = filters_applied;
            }
        },
        columns: [
            { data: 'sku_product', name: 'sku_product' },
            { data: 'sku', name: 'sku' },
            { data: 'location', name: 'location' },
            { data: 'beginning_stock_raw', name: 'beginning_stock_raw' },
            { data: 'sale_raw', name: 'sale_raw' },
            { data: 'sale_return_raw', name: 'sale_return_raw' },
            { data: 'purchase_raw', name: 'purchase_raw' },
            { data: 'purchase_return_raw', name: 'purchase_return_raw' },
            { data: 'stock_transfer_in_raw', name: 'stock_transfer_in_raw' },
            { data: 'stock_transfer_out_raw', name: 'stock_transfer_out_raw' },
            { data: 'stock_reward_in_raw', name: 'stock_reward_in_raw' },
            { data: 'stock_reward_out_raw', name: 'stock_reward_out_raw' },
            { data: 'supplier_receive_raw', name: 'supplier_receive_raw' },
            { data: 'supplier_exchange_raw', name: 'supplier_exchange_raw' },
            { data: 'stock_adjustment_raw', name: 'stock_adjustment_raw' },
            { data: 'ending_stock_raw', name: 'ending_stock_raw' }
        ],
        language: {
            emptyTable: "No data available in table",
            zeroRecords: "No matching records found."
        }
    });

    // Apply filters button click
    $('#apply_filters_btn').click(function() {
        var location_id = $('#sell_list_filter_location_id').val();
        var date_range = $('#sell_list_filter_date_range').val();
        
        // Validate required fields
        if (!location_id || location_id === '') {
            toastr.error('{{ __("Please select a location") }}');
            return;
        }
        
        if (!date_range || date_range === '') {
            toastr.error('{{ __("Please select a date range") }}');
            return;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ __("Loading...") }}');
        
        // Set filters applied flag
        filters_applied = true;
        
        // Reload DataTable
        stock_movement_report.ajax.reload();
        
        // Reset button state
        var self = this;
        setTimeout(function() {
            $(self).prop('disabled', false).html('<i class="fa fa-search"></i> {{ __("Apply Filters") }}');
        }, 1000);
    });
});
</script>
@endsection