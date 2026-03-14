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
  .table-header {
    background-color: #2DCE89;
    color: white;
    font-weight: bold;
  }
  .table-striped > tbody > tr:nth-child(odd) {
    background-color: #f9f9f9;
  }
  .table-striped > tbody > tr:nth-child(even) {
    background-color: #e8e8e8;
  }
  .label-status {
    padding: 5px;
    border-radius: 3px;
    color: white;
  }
</style>

<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <h4 class="modal-title" id="modalTitle">Purchase (<b>Reference No:</b> {{ $purchase->ref_no }})</h4>
    </div>

    <div class="modal-body">
      <!-- Visit Details -->
        <div class="row">
            <div class="col-xs-12">
            <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($purchase->transaction_date)) }}</p>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-4">
                <p><strong>Supplier:</strong> {{ $purchase->contact->name ?? 'N/A' }}</p>
            </div>
            <div class="col-xs-4">
                <p><strong>Business:</strong> {{ $purchase->location->name }}</p>
            </div>
            <div class="col-xs-4">
                <p><strong>Reference No:</strong> #{{ $purchase->ref_no }}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-4">
                <p><strong>Mobile:</strong> {{ $purchase->contact->mobile ?? 'N/A' }}</p>
            </div>
            <div class="col-xs-4">
                <p><strong>Address:</strong> {{ $purchase->location->city }}, {{ $purchase->location->state }}, {{ $purchase->location->country }}</p>
            </div>
            <div class="col-xs-4">
                <p><strong>Purchase Status:</strong> {{ ucfirst($purchase->status) }}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-4"></div>
            <div class="col-xs-4"></div>
            <div class="col-xs-4">
                <p><strong>Payment Status:</strong> {{ ucfirst($purchase->payment_status) }}</p>
                @if (!empty($order_transactions))
                    <p><strong>Order No</strong> 
                        @foreach ($order_transactions as $order)
                            #{{ $order->ref_no }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    </p>
                @endif
            </div>
        </div>

    <div style="height: 10px;"></div>

    <h4>Products Purchased</h4>

    <table class="table table-bordered table-striped">
        <thead class="table-header">
            <tr>
                <th>#</th>
                <th>Product Name</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->purchase_lines as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line->product->name ?? 'N/A' }}</td>
                    <td>
                        @if($line->sub_unit_id && $line->sub_unit)
                            {{-- If sub_unit_id is not null, calculate the quantity in terms of the sub-unit --}}
                            {{ $line->quantity / $line->sub_unit->base_unit_multiplier }} {{ $line->sub_unit->actual_name }}
                        @else
                            {{-- If sub_unit_id is null, display the normal quantity --}}
                            {{ $line->quantity }} {{ $line->product->unit->actual_name ?? 'Unit' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>
