<div class="modal-dialog" role="document" style="max-width: 600px;">
    <div class="modal-content">
        <div class="modal-header" style="background-color: #2196F3; color: white; padding: 12px 15px;">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1;">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title" style="font-size: 16px; font-weight: 500;">
                <i class="glyphicon glyphicon-edit"></i> Edit Deposit
            </h4>
        </div>

        {!! Form::open(['url' => action([\App\Http\Controllers\CashDepositController::class, 'update'], [$deposit->id]), 'method' => 'put', 'id' => 'edit_deposit_form']) !!}
        
        <div class="modal-body" style="padding: 20px;">
            
            {{-- SEARCH AND ADD NEW PAYMENTS --}}
            <div class="form-group" style="background: #f0f7ff; padding: 10px; border-radius: 4px; border: 1px solid #d0e3f7;">
                <label style="font-size: 12px; color: #2196F3;">
                    <i class="fa fa-plus-circle"></i> Add Payment (Search by Payment Ref)
                </label>
                <select id="search_add_payment" class="form-control" style="width: 100%;"></select>
            </div>

            {{-- Invoices List Section --}}
            <div class="form-group">
                <label style="font-size: 13px; color: #555;">Invoices in this Deposit</label>
                
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 10px; margin-top: 5px;">
                    <table class="table table-condensed table-striped" id="edit_invoice_table" style="margin-bottom: 0;">
                        <tbody>
                            @foreach($payments as $payment)
                            <tr id="row_payment_{{ $payment->payment_id }}">
                                <td style="vertical-align: middle;">
                                    <strong style="color: #2196F3">{{ $payment->payment_ref_no }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        {{ $payment->invoice_no }} - {{ $payment->customer_name }}
                                    </small>
                                </td>
                                <td class="text-right" style="vertical-align: middle;">
                                    <span class="display_currency" data-currency_symbol="true">{{ $payment->amount }}</span>
                                </td>
                                <td style="width: 30px; text-align: center; vertical-align: middle;">
                                    {{-- SMALL MINIMIZED BUTTON --}}
                                    <button type="button" class="btn btn-danger btn-xs remove_payment_btn" 
                                            data-id="{{ $payment->payment_id }}" 
                                            data-amount="{{ $payment->amount }}"
                                            style="padding: 2px 6px; font-size: 11px;" 
                                            title="Remove">
                                        <i class="fa fa-times" style="font-size: 16px;"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="payment_ids" id="edit_payment_ids" value="{{ implode(',', $deposit->transaction_payment_ids ?? []) }}">
            </div>

            {{-- Totals Section --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="text" class="form-control" id="edit_display_amount" readonly 
                               value="{{ number_format($deposit->amount, 2) }}"
                               style="background-color: #f5f5f5; font-weight: bold;">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Deposit Date *</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            {!! Form::text('deposit_datetime', @format_datetime($deposit->deposit_datetime), ['class' => 'form-control', 'required', 'readonly', 'id' => 'edit_deposit_datetime']); !!}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Bank Account *</label>
                        {!! Form::select('bank_account_id', $bank_accounts, $deposit->bank_account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'required']); !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Reference No</label>
                        {!! Form::text('ref_no', $deposit->ref_no, ['class' => 'form-control']); !!}
                    </div>
                </div>
            </div>

            {{-- Media Section --}}
            <div class="form-group">
                <label>Attach Slip / Payment Proof</label>
                
                @if($deposit->media && $deposit->media->count() > 0)
                    <div style="margin-bottom: 10px; background: #f9f9f9; padding: 10px; border: 1px dashed #ccc;">
                        <ul class="list-unstyled" style="margin-bottom: 0;">
                            @foreach($deposit->media as $media)
                                <li style="margin-bottom: 5px; padding: 3px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;" id="media_item_{{ $media->id }}">
                                    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 90%;">
                                        @if(in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']))
                                            <i class="fa fa-file-image-o text-success"></i>
                                        @else
                                            <i class="fa fa-file-pdf-o text-danger"></i>
                                        @endif
                                        <a href="{{ $media->display_url }}" target="_blank" style="margin-left: 5px;">{{ $media->display_name }}</a>
                                    </div>
                                    
                                    {{-- SMALL DELETE BUTTON FOR MEDIA --}}
                                    <button type="button" class="btn btn-danger btn-xs delete_media_btn" 
                                            data-href="{{ route('cash_deposit.delete_media', [$media->id]) }}" 
                                            data-id="{{ $media->id }}"
                                            style="padding: 2px 6px; font-size: 11px;"
                                            title="Delete">
                                        <i class="fa fa-times" style="font-size: 16px;"></i>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="dropzone" id="editDepositSlipUpload"></div>
                <input type="hidden" id="edit_deposit_slip_names" name="deposit_slip_names" value="">
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="update_deposit_btn">
                <i class="fa fa-save"></i> Update Changes
            </button>
        </div>

        {!! Form::close() !!}
    </div>
</div>