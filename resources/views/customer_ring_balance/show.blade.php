<!DOCTYPE html>
<html>
<head>
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
        .sell-note-section {
            margin-top: 20px;
            background-color: #f8f9fa;
        }
        .sell-note-label {
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
        }
        .sell-note-content {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 3px;
            min-height: 40px;
            border: 1px solid #ced4da;
        }
        .no-note {
            color: #6c757d;
            font-style: italic;
        }
        .total-footer {
            margin-top: 15px;
            text-align: right;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</head>
<body>
    {{-- Initialize Totals --}}
    @php
        $totalRiel = 0;
        $totalDollar = 0;
    @endphp

    <div class="modal-dialog modal-xl no-print" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalTitle">@lang('Top Up Details') (<b>Reference No:</b> {{ $transaction->invoice_no }})</h4>
            </div>
            <div class="modal-body">
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
                        <b>Invoice Sell No:</b> {{ $transaction->sell_ref_invoice ? $transaction->sell_ref_invoice : 'N/A' }}
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
                            <th>Product Name</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            {{-- Added Subtotal Column --}}
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaction->transactionSellRingBalances as $transactionSell)
                            {{-- Product Total Row --}}
                            <tr class="product-total-row">
                                <td>{{ $transactionSell->product->name }}</td>
                                <td><strong>Total</strong></td>
                                <td><strong>{{ number_format($transactionSell->quantity, 0) }}</strong></td>
                                <td></td> {{-- Empty Subtotal for Header Row --}}
                            </tr>

                            {{-- Check if this is cash ring (cash_ring = 1) --}}
                            @if($transactionSell->cash_ring == 1)
                                {{-- Display cash ring balance details --}}
                                @foreach($transactionSell->cashRingBalanceDetails as $cashDetail)
                                    @php
                                        // Calculate Subtotal
                                        $unitValue = $cashDetail->cashRingBalance->unit_value;
                                        $qty = $cashDetail->quantity;
                                        $subtotal = $unitValue * $qty;
                                        $isDollar = $cashDetail->cashRingBalance->type_currency == 1; // 1 = Dollar, 2 = Riel
                                        
                                        // Add to Grand Totals
                                        if ($isDollar) {
                                            $totalDollar += $subtotal;
                                            $symbol = '$';
                                        } else {
                                            $totalRiel += $subtotal;
                                            $symbol = '៛';
                                        }
                                    @endphp

                                    <tr class="detail-row">
                                        <td style="padding-left: 30px;"></td>
                                        <td>{{ number_format($unitValue, 0) }} ({{ $symbol }})</td>
                                        <td>{{ number_format($qty, 0) }}</td>
                                        {{-- Display Subtotal --}}
                                        <td>{{ number_format($subtotal, 0) }} {{ $symbol }}</td>
                                    </tr>
                                @endforeach
                            @else
                                {{-- Display ring unit details for non-cash ring (Customer Ring) --}}
                                @foreach($transactionSell->ringUnitDetails as $ringDetail)
                                    <tr class="detail-row">
                                        <td style="padding-left: 30px;"></td>
                                        <td>{{ number_format($ringDetail->ringUnit->value, 0) }} Can</td>
                                        <td>{{ number_format($ringDetail->quantity_ring, 0) }}</td>
                                        <td></td> {{-- Empty Subtotal for non-cash rings --}}
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>

                {{-- Total Footer Section --}}
                <div class="row">
                    <div class="col-md-12 total-footer">
                        Total Riel: {{ number_format($totalRiel, 0) }}៛ 
                        &nbsp;&nbsp;&nbsp;&nbsp; 
                        Total Dollar: ${{ number_format($totalDollar, 2) }}
                    </div>
                </div>

                <div class="sell-note-section">
                    <div class="sell-note-label">Sell note:</div>
                    <div class="sell-note-content">
                        @if(!empty($transaction->noted) && trim($transaction->noted) !== '')
                            {{ $transaction->noted }}
                        @else
                            <span class="no-note">--</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</body>
</html>