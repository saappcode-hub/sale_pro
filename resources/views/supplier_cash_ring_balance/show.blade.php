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
  .product-total-row {
    background-color: #D2D6DE !important;
    color: black;
    font-weight: bold;
  }
  .detail-row {
    background-color: #f8f9fa;
    color: #495057;
  }
   .note-row {
    background-color: #fff3cd !important;
    color: #856404;
    border-left: 4px solid #ffc107;
  }
</style>
<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <h4 class="modal-title" id="modalTitle">@lang('Supplier Cash Ring Exchange Details') (<b>Reference No:</b> {{ $transaction->invoice_no }})</h4>
    </div>
    <div class="modal-body">
      <!-- Transaction Details -->
      <div class="row">
        <div class="col-xs-4">
          <b>Reference No:</b> #{{ $transaction->invoice_no }}
        </div>
        <div class="col-xs-8">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($transaction->transaction_date)) }}</p>
        </div>
      </div>
      <div class="row">
        <div class="col-xs-4">
          <b>Location:</b> {{ $transaction->location_name ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Supplier Name:</b> {{ $transaction->supplier_name ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Status:</b>
          @if($transaction->status == 'pending')
            <span class="status-pending">Pending</span>
          @elseif($transaction->status == 'send')
            <span class="status-completed">Send</span>
          @else
            <span>{{ ucfirst($transaction->status) }}</span>
          @endif
        </div>
      </div>
      <h5 style="font-size: 17px; margin-top: 20px;">Products:</h5>
      <table class="table table-striped">
        <thead>
          <tr class="table-header">
            <th>#</th>
            <th>Exchange Product</th>
            <th>Unit Values</th>
            <th>Qty</th>
            <th>Sub Total</th>
          </tr>
        </thead>
        <tbody>
          @php
            $productGroups = $transactionDetails->groupBy('product_id');
            $rowNumber = 1;
          @endphp
          
          @foreach ($productGroups as $productId => $details)
            @php
              $firstDetail = $details->first();
              $productName = $firstDetail->product_name ?? 'Unknown Product';
              $totalQuantity = $details->sum('quantity');
              
              // Calculate totals by currency
              $dollarTotal = 0;
              $rielTotal = 0;
              
              foreach($details as $detail) {
                $unitValue = $detail->unit_value ?? 0;
                $redemptionValue = $detail->redemption_value ?? 0;
                $quantity = $detail->quantity;
                
                if($detail->type_currency == 1) {
                  // Dollar
                  $dollarTotal += $quantity * $redemptionValue;
                } else {
                  // Riel
                  $rielTotal += $quantity * $redemptionValue;
                }
              }
            @endphp
            
            {{-- Product Total Row --}}
            <tr class="product-total-row">
              <td>{{ $rowNumber }}</td>
              <td>{{ $productName }}</td>
              <td><strong>Total</strong></td>
              <td><strong>{{ number_format($totalQuantity, 0) }}</strong></td>
              <td>
                <strong>
                  @if($dollarTotal > 0)
                    ${{ number_format($dollarTotal, 2) }}
                  @endif
                  @if($dollarTotal > 0 && $rielTotal > 0)
                    <br>
                  @endif
                  @if($rielTotal > 0)
                    {{ number_format($rielTotal, 0) }}៛
                  @endif
                </strong>
              </td>
            </tr>

            {{-- Cash Ring Balance Details --}}
            @foreach($details as $detail)
              @php
                $unitValue = $detail->unit_value ?? 0;
                $redemptionValue = $detail->redemption_value ?? 0;
                $currencySymbol = ($detail->type_currency == 1) ? '$' : '៛';
                $subtotal = $detail->quantity * $redemptionValue;
              @endphp
              
              <tr class="detail-row">
                <td style="padding-left: 30px;"></td>
                <td style="padding-left: 30px;"></td>
                <td>{{ number_format($unitValue, 0) }}{{ $currencySymbol }}</td>
                <td>{{ number_format($detail->quantity, 0) }}</td>
                <td>
                  @if($detail->type_currency == 1)
                    ${{ number_format($subtotal, 2) }}
                  @else
                    {{ number_format($subtotal, 0) }}៛
                  @endif
                </td>
              </tr>
            @endforeach
            
            @php $rowNumber++; @endphp
          @endforeach
        </tbody>
        <tfoot>
          <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="4" class="text-right"><strong>Total ($) :</strong></td>
            <td><strong>${{ number_format($transaction->total_amount_dollar ?? 0, 2) }}</strong></td>
          </tr>
          <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="4" class="text-right"><strong>Total (៛) :</strong></td>
            <td><strong>{{ number_format($transaction->total_amount_riel ?? 0, 0) }}៛</strong></td>
          </tr>
        </tfoot>
      </table>
      <div class="row">
        <div class="col-xs-12">
          <div style="background-color: #ddd; padding: 8px 15px; border-radius: 3px;">
            <b>Note:</b> {{ $transaction->note }}
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>