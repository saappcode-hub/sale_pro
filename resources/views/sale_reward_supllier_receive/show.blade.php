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

  .photo-container {
    overflow-x: auto;
    white-space: nowrap;
  }

  .photo-container img {
    display: inline-block;
    vertical-align: top;
  }

  .table-header {
    background-color: #2DCE89;
    color: white;
    font-weight: bold;
  }

  .total-row {
    font-weight: bold;
    color: black;
  }

  .total-label {
    text-align: right;
    padding-right: 10px;
  }

  .table-striped > tbody > tr:nth-child(odd) {
    background-color: #f9f9f9;
  }

  .table-striped > tbody > tr:nth-child(even) {
    background-color: #e8e8e8;
  }

  .sell-note {
    background-color: #d2d6de;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-weight: bold;
    color: black;
    width: 100%;
  }

  .sell-note-label {
    font-weight: bold;
    color: #343a40;
    margin-bottom: 5px;
  }

  .table-striped > tbody > tr:nth-child(2n) {
    background-color: white;
    border-top: 1px solid white;
  }
  .table > tbody > tr > td{
    border-top: 1px solid white;
  }
</style>

<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <h4 class="modal-title" id="modalTitle">@lang('Reward Details') (<b>Reference No:</b> {{ $transaction->ref_no }})</h4>
    </div>

    <div class="modal-body">
      <!-- Visit Details -->
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($transaction->transaction_date)) }}</p>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-4">
          <b>Reference No:</b> #{{ $transaction->ref_no }}
        </div>
        <div class="col-xs-4">
          <b>Supplier Name:</b> {{ $transaction->contact->name }}
        </div>
        <div class="col-xs-4">
          <b>Received Status:</b>
          @if($transaction->status == 'pending')
            <span class="status-pending">Pending</span>
          @elseif($transaction->status == 'completed')
            <span class="status-completed">Completed</span>
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
                  <th>Price</th>
                  <th>Qty</th>
                  <th>Sub Total</th>
                  <th>Receive Product</th>
              </tr>
          </thead>
          <tbody>
              @foreach ($transaction->sell_lines->values() as $index => $line)
                  @php
                      $received = $received_products->get($index);
                      $receiveProductName = $received->receive_product_name ?? 'N/A';
                      $quantity_display = (int) $line->quantity;
                  @endphp

                  <tr style="background-color: #D2D6DE; color: black;">
                      <td>{{ $index + 1 }}</td>
                      <td>{{ $line->product->name ?? 'N/A' }}</td>
                      <td>${{ number_format((float)$line->unit_price, 2) }}</td>
                      <td>{{ number_format($quantity_display, 0) }}</td>
                      <td>${{ number_format((float)$line->quantity * (float)$line->unit_price, 2) }}</td>
                      <td>{{ $receiveProductName }}</td>
                  </tr>
              @endforeach

              <tr><td></td></tr>
              <tr class="total-row">
                  <td colspan="3" style="background-color: white;"></td>
                  <td colspan="2" class="total-label" style="background-color: #D2D6DE; color: black; text-align: left;">
                      <strong>Total:</strong>
                  </td>
                  <td colspan="2" style="text-align: right; background-color: #D2D6DE; color: black;">
                      <strong>${{ number_format($transaction->final_total, 2) }}</strong>
                  </td>
              </tr>
          </tbody>
      </table>

      <div class="sell-note-label">Note:</div>
      @if (!empty($transaction->additional_notes))
        <div class="sell-note">
          {{ $transaction->additional_notes }}
        </div>
      @endif
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>