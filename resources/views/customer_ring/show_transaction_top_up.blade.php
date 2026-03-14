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
</style>

<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <h4 class="modal-title" id="modalTitle">@lang('Top Up Details') (<b>Reference No:</b> {{ $transaction->invoice_no }})</h4>
    </div>

    <div class="modal-body">
      <!-- Transaction Details -->
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($transaction->transaction_date)) }}</p>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-4">
          <b>Reference No:</b> #{{ $transaction->invoice_no }}
        </div>
        <div class="col-xs-4">
          <b>Customer Name:</b> {{ $transaction->contact->name }}
        </div>
        <div class="col-xs-4">
          <b>Status:</b>
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
            <th>Product Name</th>
            <th>Quantity</th>
          </tr>
        </thead>
        <tbody>
          @php 
            $index = 1; 
          @endphp
          @foreach ($transaction->transactionSellRingBalances as $transactionSell)
            <tr style="background-color: #D2D6DE; color: black;">
              <td>{{ $index++ }}</td>
              <!-- Display the product name -->
              <td>{{ $transactionSell->product->name }}</td>
              <td>{{ $transactionSell->quantity }}</td>
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
