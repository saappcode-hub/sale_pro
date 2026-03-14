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
                        <h3>{{ $selected_product->name }}</h3>
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
                </div>
                <div style="height: 15px;"></div>
                <div class="col-md-12">
                    <div class="col-md-6">
                      <b> Quantities In </b>
                    </div>  
                    <div class="col-md-6">
                        <b> Quantities Out </b>
                    </div>         
                </div>
                <div class="col-md-12">
                    <div class="col-md-6">
                        <div class="col-md-3">
                            <b> Ring Top Up </b>
                        </div>
                        <div class="col-md-3">
                            <b> {{ number_format($product_data->quantities_in, 2) }}  {{ $product_data->unit_short_name }} </b>
                        </div>
                    </div>  
                    <div class="col-md-6">
                        <div class="col-md-3">
                            <b> Reward Out </b>
                        </div>
                        <div class="col-md-3">
                            <b> {{ number_format($product_data->used_ring_balance, 2) }} {{ $product_data->unit_short_name }} </b>
                        </div>
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
                            <div class="tab-pane" id="ring_stock_history">
                                <div class="box-body">
                                    <table style="width: 100%;" class="table table-bordered table-striped" id="ring_stock_history_table">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Quantity Change</th>
                                                <th>New Quantity</th>
                                                <th>Date</th>
                                                <th>Reference No</th>
                                                <th>Customer</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
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
    $(document).ready(function() {
       var ringStockHistoryTable = $('#ring_stock_history_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('customer-ring.getRingStockHistory') }}",
            type: "GET",
            data: function(d) {
                d.product_id = $('#product_filter').val();
                d.contact_id = "{{ $contact_id }}";
            },
            error: function (xhr, error, code) {
                console.log(xhr);
                console.log(code);
            },
        },
        columns: [
            { data: 'type', name: 'type' },
            { data: 'quantity_change', name: 'quantity_change', orderable: false, searchable: false },
            { data: 'new_quantity', name: 'new_quantity' },
            { data: 'date', name: 'date', orderable: false }, // Disable sorting on date column
            { data: 'invoice_no', name: 'invoice_no' },
            { data: 'customer', name: 'customer' }
        ],
        order: [], // Remove default ordering - let server handle it
        drawCallback: function(settings) {
            console.log('DataTable drawCallback executed');
        }
    });

        // Reload table when product filter changes
        $('#product_filter').on('change', function () {
            ringStockHistoryTable.ajax.reload();
        });
    });
</script>

@endsection
