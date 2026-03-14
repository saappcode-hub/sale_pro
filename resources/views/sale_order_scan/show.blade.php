<style>
  .status-partial {
    background-color: #328AC9;
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
      <h4 class="modal-title" id="modalTitle">@lang('Scan Details') (<b>Invoice No:</b> {{ $transactionScan->ref_no }})</h4>
    </div>

    <div class="modal-body">
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($transactionScan->created_at)) }}</p>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-4">
          <b>Created By:</b> {{ $transactionScan->createdByUser->username ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Scan Date:</b> {{ date('d-m-Y H:i:s', strtotime($transactionScan->created_at)) ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Status:</b>
          @if($transactionScan->status == 'partial')
            <span class="status-partial">Partial</span>
          @elseif($transactionScan->status == 'completed')
            <span class="status-completed">Completed</span>
          @else
            <span>{{ ucfirst($transactionScan->status) }}</span>
          @endif
        </div>
      </div>
      <div style="height: 10px;"></div>
      <div class="row">
        <div class="col-xs-4">
          <b>Updated By:</b> {{ $transactionScan->updatedByUser->username ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Updated Date:</b> {{ date('d-m-Y H:i:s', strtotime($transactionScan->updated_at)) ?? 'N/A' }}
        </div>
      </div>

      <h5 style="font-size: 17px; margin-top: 20px;">Products:</h5>
      <table class="table table-striped">
        <thead>
          <tr class="table-header">
            <th>#</th>
            <th>Product Name</th>
            <th>SKU</th>
            <th>Quantity Order</th>
            <th>Quantity Scan</th>
          </tr>
        </thead>
        <tbody>
          @php $index = 1; @endphp
          @foreach ($transactionScan->TransactionSellLineScan as $transactionSell)
            <tr>
              <td>{{ $index++ }}</td>
              <td>{{ $transactionSell->product->name ?? 'Unknown Product' }}</td>
              <td>{{ $transactionSell->product->sku ?? 'N/A' }}</td>
              <td>{{ $transactionSell->quantity_order }}</td>
              <td @if($transactionSell->quantity_scan < $transactionSell->quantity_order) style="color: red;" @endif>
                {{ $transactionSell->quantity_scan }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="modal-footer">
      <button type of "button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>
