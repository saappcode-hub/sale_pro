@extends('layouts.app')

@section('title', __('Ring Stock History'))

@section('content')
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
    .info-section {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }
    .info-label {
        font-weight: bold;
        font-size: 16px;
    }
    .info-value {
        font-weight: bold;
        font-size: 16px;
    }
    .ring-unit-list {
        text-align: right;
    }
    .ring-unit-list div {
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 16px;
    }
</style>

<section class="content-header">
    <div class="row">
        <div class="col-md-12">
            <div class="col-md-6">
                <h1>{{ __('Ring Stock History') }}</h1>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="col-md-6">
                        <h3>{{ $selected_product->name }} ({{ $selected_product->sku }})</h3>
                    </div>
                </div>
                <div style="height: 15px;"></div>
                <div class="col-md-12">
                    <!-- Dropdown for Products -->
                    <div class="col-md-6">
                        {!! Form::label('product_filter', __('Ring Name:')) !!}
                        {!! Form::select('sell_list_filter_product_id', $exchange_products, $selected_product->id, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'product_filter'
                        ]) !!}
                    </div>

                    <!-- Dropdown for Business Locations -->
                    <div class="col-md-6">
                        {!! Form::label('business_location_filter', __('Business Location:')) !!}
                        {!! Form::select('sell_list_filter_location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'business_location_filter'
                        ]) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="col-md-6">
                        <h3>{{ $selected_product->name }} ({{$selected_product->sku}})</h3>
                    </div>
                    <div class="col-md-6">
                        <h3>Ring Unit Detail</h3>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="col-md-6">
                        <div class="info-section">
                            <span class="info-label">Customer Ring Top Up</span>
                            <span class="info-value">{{ number_format($product_data->quantities_in, 2) }} {{ $product_data->unit_short_name }}</span>
                        </div>
                        <div class="info-section">
                            <span class="info-label">Customer Rewards Exchange</span>
                            <span class="info-value">{{ number_format($product_data->used_ring_balance, 2) }} {{ $product_data->unit_short_name }}</span>
                        </div>
                        <div class="info-section">
                            <span class="info-label">Supplier Rewards Exchange</span>
                            <span class="info-value">{{ number_format($supplier_exchange->supplier_exchange_send, 2, '.', '') }} {{ $product_data->unit_short_name }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        @foreach($ring_unit_details as $ring_unit)
                            <div class="info-section">
                                <span class="info-label">{{ $ring_unit->value }} Can</span>
                                <span class="info-value">{{ number_format($ring_unit->total_quantity_ring, 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-12">
                    @component('components.widget', ['class' => 'box-primary'])
                        <!-- Transaction Top Up Tab -->
                        <div class="tab-pane active" id="ring_stock_history">
                            <div class="box-body">
                                <table style="width: 100%;" class="table table-bordered table-striped" id="ring_stock_history_table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Quantity Change</th>
                                            <th>New Quantity</th>
                                            <th>Date</th>
                                            <th>Reference No</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    @endcomponent
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
   $(document).ready(function () {
        var ringStockHistoryTable = $('#ring_stock_history_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('all-ring.getRingStockHistory') }}",
                type: "GET",
                data: function (d) {
                    d.product_id = $('#product_filter').val(); // Pass the selected product ID
                },
                error: function (xhr, error, code) {
                    console.log(xhr.responseJSON.error || 'An error occurred');
                },
            },
            columns: [
                { data: 'type', name: 'type' },
                { data: 'quantity_change', name: 'quantity_change', orderable: false, searchable: false },
                { data: 'new_quantity', name: 'new_quantity' },
                { data: 'date', name: 'date' },
                { data: 'invoice_no', name: 'invoice_no' },
            ],
            order: [[3, 'desc']], // Ensure display order matches the descending order from the backend
        });

        $('#product_filter').on('change', function () {
            ringStockHistoryTable.ajax.reload();
        });
    });
</script>
@endsection