@extends('layouts.app')

@section('title', __('Cash Ring Stock History'))

@section('content')
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
                <h1>{{ __('Cash Ring Stock History') }}</h1>
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
                        {!! Form::label('product_filter', __('Cash Ring Name:')) !!}
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
                        <h3>Cash Ring Detail($)</h3>
                    </div>
                    <div class="col-md-6">
                        <h3>Cash Ring Detail(៛)</h3>
                    </div>
                </div>
                <div class="col-md-12">
                    <!-- Dollar Section -->
                    <div class="col-md-6">
                        @if($dollar_data->isNotEmpty())
                            @foreach($dollar_data as $cash_ring)
                                <div class="info-section">
                                    <span class="info-label">${{ number_format($cash_ring->unit_value, 0) }}</span>
                                    <span class="info-value">{{ number_format($cash_ring->total_quantity, 0) }}</span>
                                </div>
                            @endforeach
                            <div class="info-section" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                                <span class="info-label">Sub Total:</span>
                                <span class="info-value">{{ number_format($dollar_subtotal, 0) }}</span>
                            </div>
                        @else
                            <div class="info-section">
                                <span class="info-label">No dollar cash ring data</span>
                                <span class="info-value">0</span>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Riel Section -->
                    <div class="col-md-6">
                        @if($riel_data->isNotEmpty())
                            @foreach($riel_data as $cash_ring)
                                <div class="info-section">
                                    <span class="info-label">{{ number_format($cash_ring->unit_value, 0) }}៛</span>
                                    <span class="info-value">{{ number_format($cash_ring->total_quantity, 0) }}</span>
                                </div>
                            @endforeach
                            <div class="info-section" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                                <span class="info-label">Sub Total:</span>
                                <span class="info-value">{{ number_format($riel_subtotal, 0) }}</span>
                            </div>
                        @else
                            <div class="info-section">
                                <span class="info-label">No riel cash ring data</span>
                                <span class="info-value">0</span>
                            </div>
                        @endif
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
                        <!-- Cash Ring Transaction History -->
                        <div class="tab-pane active" id="cash_ring_stock_history">
                            <div class="box-body">
                                <table style="width: 100%;" class="table table-bordered table-striped" id="cash_ring_stock_history_table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Quantity Change</th>
                                            <th>Value Change</th>
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
    var cashRingStockHistoryTable = $('#cash_ring_stock_history_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('all-ring.getCashRingStockHistory', $selected_product->id) }}", // Pass product ID in URL
            type: "GET",
            data: function (d) {
                // No need to pass product_id in data since it's in the URL now
                console.log('DataTable request data:', d); // Debug log
                return d;
            },
            error: function (xhr, error, code) {
                console.log('AJAX Error:', xhr.responseJSON ? xhr.responseJSON.error : 'An error occurred');
                console.log('Error details:', xhr, error, code);
                console.log('Response Text:', xhr.responseText);
            },
        },
        columns: [
            { data: 'type', name: 'type' },
            { data: 'quantity_change', name: 'quantity_change', orderable: false, searchable: false },
            { data: 'value_change', name: 'value_change', orderable: false, searchable: false },
            { data: 'new_quantity', name: 'new_quantity' },
            { data: 'date', name: 'date' },
            { data: 'invoice_no', name: 'invoice_no' },
        ],
        order: [[4, 'desc']], // Order by date descending
    });

    $('#product_filter').on('change', function () {
        var selectedProductId = $(this).val();
        console.log('Product filter changed to:', selectedProductId); // Debug log
        if (selectedProductId) {
            // Redirect to the new product's cash ring show page
            window.location.href = "{{ route('all-ring.showCashRing', '') }}/" + selectedProductId;
        }
    });
});
</script>
@endsection