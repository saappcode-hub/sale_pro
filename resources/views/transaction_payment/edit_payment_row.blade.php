<div class="modal-dialog" role="document" style="width: 1000px;">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\TransactionPaymentController::class, 'update'], [$payment_line->id]), 'method' => 'put', 'id' => 'transaction_payment_add_form', 'files' => true ]) !!}
    @if(!empty($transaction->location))
      {!! Form::hidden('default_payment_accounts', $transaction->location->default_payment_accounts, ['id' => 'default_payment_accounts']); !!}
    @endif
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'purchase.edit_payment' )</h4>
    </div>

    <div class="modal-body">
      <input type="hidden" id="transaction_final_total" value="{{ $transaction->final_total }}">
      
      {{-- 🔑 REQUIRED: Hidden Inputs for Payment Back Date Logic --}}
      <input type="hidden" id="payment_back_date" value="{{ $payment_back_date ?? 0 }}">
      {{-- Stores the original paid date for comparison in the JS logic --}}
      <input type="hidden" id="original_paid_on" value="{{ $original_paid_on ?? '' }}">

      <div class="row">
        @if(!empty($transaction->contact))
        <div class="col-md-4">
          <div class="well">
            <strong>@if($transaction->contact->type == 'supplier') @lang('purchase.supplier'): @else @lang('contact.customer'): @endif </strong>{{ $transaction->contact->full_name_with_business }}<br>
            <strong>@lang('business.business'): </strong>{{ $transaction->contact->supplier_business_name }}
          </div>
        </div>
        @endif
        @if($transaction->type != 'opening_balance')
        <div class="col-md-4">
          <div class="well">
            <strong>@lang('purchase.ref_no'): </strong>{{ $transaction->ref_no }}<br>
            @if(!empty($transaction->location))
              <strong>@lang('purchase.location'): </strong>{{ $transaction->location->name }}
            @endif
          </div>
        </div>
        <div class="col-md-4">
          <div class="well">
            <strong>@lang('sale.total_amount'): </strong><span class="display_currency" data-currency_symbol="true">{{ $transaction->final_total }}</span><br>
            <strong>@lang('purchase.payment_note'): </strong>
            @if(!empty($transaction->additional_notes))
            {{ $transaction->additional_notes }}
            @else
              --
            @endif
          </div>
        </div>
        @endif
      </div>

      <div id="payment_rows_container">
        <div class="payment_row_block" data-row-index="0" style="background: #f9f9f9; margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; position: relative;">
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                {!! Form::label("payment[0][method]" , __('purchase.payment_method') . ':*') !!}
                <div class="input-group">
                  <span class="input-group-addon">
                    <i class="fas fa-money-bill-alt"></i>
                  </span>
                  {!! Form::select("payment[0][method]", $payment_types, $payment_line->method, ['class' => 'form-control select2 payment_types_dropdown', 'required', 'style' => 'width:100%;']); !!}
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                {!! Form::label("payment[0][paid_on]" , __('lang_v1.paid_on') . ':*') !!}
                <div class="input-group">
                  <span class="input-group-addon">
                    <i class="fa fa-calendar"></i>
                  </span>
                  {{-- 🔑 NOTE: Removed extra data attributes here because the JS logic handles it via disabledDates now --}}
                  {!! Form::text('payment[0][paid_on]', @format_datetime($payment_line->paid_on), ['class' => 'form-control paid_on', 'readonly', 'required']); !!}
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                {!! Form::label("payment[0][amount]" , __('sale.amount') . ':*') !!}
                <div class="input-group">
                  <span class="input-group-addon">
                    <i class="fas fa-money-bill-alt"></i>
                  </span>
                  {!! Form::text("payment[0][amount]", @num_format($payment_line->method == 'cash_ring_percentage' ? $payment_line->cash_ring_percentage : $payment_line->amount), ['class' => 'form-control input_number payment_amount', 'required', 'placeholder' => 'Amount']); !!}
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                {!! Form::label('payment[0][document]', __('purchase.attach_document') . ':') !!}
                {!! Form::file('payment[0][document]', ['accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
                @if(!empty($payment_line->document))
                <p class="help-block">@lang('lang_v1.previous_file_will_be_replaced'): {{ $payment_line->document }}</p>
                @endif
              </div>
            </div>
          </div>

          @php
              $pos_settings = !empty(session()->get('business.pos_settings')) ? json_decode(session()->get('business.pos_settings'), true) : [];
              $enable_cash_denomination_for_payment_methods = !empty($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : [];
          @endphp

          @if(!empty($pos_settings['enable_cash_denomination_on']) && $pos_settings['enable_cash_denomination_on'] == 'all_screens')
              <input type="hidden" class="enable_cash_denomination_for_payment_methods" value="{{json_encode($pos_settings['enable_cash_denomination_for_payment_methods'])}}">
              <div class="clearfix"></div>
              <div class="col-md-12 cash_denomination_div @if(!in_array($payment_line->method, $enable_cash_denomination_for_payment_methods)) hide @endif">
                  <hr>
                  <strong>@lang( 'lang_v1.cash_denominations' )</strong>
                    @if(!empty($pos_settings['cash_denominations']))
                      <table class="table table-slim">
                        <thead>
                          <tr>
                            <th width="20%" class="text-right">@lang('lang_v1.denomination')</th>
                            <th width="20%">&nbsp;</th>
                            <th width="20%" class="text-center">@lang('lang_v1.count')</th>
                            <th width="20%">&nbsp;</th>
                            <th width="20%" class="text-left">@lang('sale.subtotal')</th>
                          </tr>
                        </thead>
                        <tbody>
                          @php
                              $total = 0;
                          @endphp
                          @foreach(explode(',', $pos_settings['cash_denominations']) as $dnm)
                          @php
                              $count = 0;
                              $sub_total = 0;
                              foreach($payment_line->denominations as $d) {
                                  if($d->amount == $dnm) {
                                      $count = $d->total_count; 
                                      $sub_total = $d->total_count * $d->amount;
                                      $total += $sub_total;
                                  }
                              }
                          @endphp
                          <tr>
                            <td class="text-right">{{$dnm}}</td>
                            <td class="text-center" >X</td>
                            <td>{!! Form::number("payment[0][denominations][$dnm]", $count, ['class' => 'form-control cash_denomination input-sm', 'min' => 0, 'data-denomination' => $dnm, 'style' => 'width: 100px; margin:auto;' ]); !!}</td>
                            <td class="text-center">=</td>
                            <td class="text-left">
                              <span class="denomination_subtotal">{{@num_format($sub_total)}}</span>
                            </td>
                          </tr>
                          @endforeach
                        </tbody>
                        <tfoot>
                          <tr>
                            <th colspan="4" class="text-center">@lang('sale.total')</th>
                            <td>
                              <span class="denomination_total">{{@num_format($total)}}</span>
                              <input type="hidden" class="denomination_total_amount" value="{{$total}}">
                              <input type="hidden" class="is_strict" value="{{$pos_settings['cash_denomination_strict_check'] ?? ''}}">
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                      <p class="cash_denomination_error error hide">@lang('lang_v1.cash_denomination_error')</p>
                    @else
                      <p class="help-block">@lang('lang_v1.denomination_add_help_text')</p>
                    @endif
              </div>
              <div class="clearfix"></div>
          @endif

          @if(!empty($accounts))
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  {!! Form::label("payment[0][account_id]" , __('lang_v1.payment_account') . ':') !!}
                  <div class="input-group">
                    <span class="input-group-addon">
                      <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::select("payment[0][account_id]", $accounts, !empty($payment_line->account_id) ? $payment_line->account_id : '' , ['class' => 'form-control select2', 'id' => "account_id", 'style' => 'width:100%;']); !!}
                  </div>
                </div>
              </div>
            </div>
          @endif
          
          <div class="clearfix"></div>
          
          <div class="payment_details_div @if( $payment_line->method !== 'card' ) {{ 'hide' }} @endif" data-type="card">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  {!! Form::label("card_number_0", __('lang_v1.card_no')) !!}
                  {!! Form::text("payment[0][card_number]", $payment_line->card_number ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.card_no'), 'id' => "card_number_0"]); !!}
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  {!! Form::label("card_holder_name_0", __('lang_v1.card_holder_name')) !!}
                  {!! Form::text("payment[0][card_holder_name]", $payment_line->card_holder_name ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.card_holder_name'), 'id' => "card_holder_name_0"]); !!}
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  {!! Form::label("card_transaction_number_0",__('lang_v1.card_transaction_no')) !!}
                  {!! Form::text("payment[0][card_transaction_number]", $payment_line->card_transaction_number ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.card_transaction_no'), 'id' => "card_transaction_number_0"]); !!}
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  {!! Form::label("card_type_0", __('lang_v1.card_type')) !!}
                  {!! Form::select("payment[0][card_type]", ['credit' => 'Credit Card', 'debit' => 'Debit Card','visa' => 'Visa', 'master' => 'MasterCard'], $payment_line->card_type ?? '',['class' => 'form-control select2', 'id' => "card_type_0" ]); !!}
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  {!! Form::label("card_month_0", __('lang_v1.month')) !!}
                  {!! Form::text("payment[0][card_month]", $payment_line->card_month ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.month'), 'id' => "card_month_0" ]); !!}
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  {!! Form::label("card_year_0", __('lang_v1.year')) !!}
                  {!! Form::text("payment[0][card_year]", $payment_line->card_year ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.year'), 'id' => "card_year_0" ]); !!}
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  {!! Form::label("card_security_0",__('lang_v1.security_code')) !!}
                  {!! Form::text("payment[0][card_security]", $payment_line->card_security ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.security_code'), 'id' => "card_security_0"]); !!}
                </div>
              </div>
            </div>
          </div>

          <div class="payment_details_div @if( $payment_line->method !== 'cheque' ) {{ 'hide' }} @endif" data-type="cheque">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  {!! Form::label("cheque_number_0",__('lang_v1.cheque_no')) !!}
                  {!! Form::text("payment[0][cheque_number]", $payment_line->cheque_number ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.cheque_no'), 'id' => "cheque_number_0"]); !!}
                </div>
              </div>
            </div>
          </div>

          <div class="payment_details_div @if( $payment_line->method !== 'bank_transfer' ) {{ 'hide' }} @endif" data-type="bank_transfer">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  {!! Form::label("bank_account_number_0",__('lang_v1.bank_account_number')) !!}
                  {!! Form::text( "payment[0][bank_account_number]", $payment_line->bank_account_number ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.bank_account_number'), 'id' => "bank_account_number_0"]); !!}
                </div>
              </div>
            </div>
          </div>

          <div class="payment_details_div @if( $payment_line->method !== 'cash_ring_percentage' ) {{ 'hide' }} @endif" data-type="cash_ring_percentage">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  {!! Form::label("cash_ring_percentage_0", __('Percentage') . '*') !!}
                  <div class="input-group">
                    <span class="input-group-addon">
                      <i class="fas fa-percentage"></i>
                    </span>
                    @php
                      // Calculate percentage from existing data
                      $percentage = 0;
                      if ($payment_line->method == 'cash_ring_percentage' && !empty($payment_line->cash_ring_percentage) && !empty($payment_line->amount)) {
                          if (isset($payment_line->percentage)) {
                              $percentage = $payment_line->percentage;
                          } else {
                              $base_amount = floatval($payment_line->cash_ring_percentage);
                              $final_amount = floatval($payment_line->amount);
                              if ($base_amount > 0) {
                                  $percentage = (($final_amount - $base_amount) / $base_amount) * 100;
                              }
                          }
                      }
                    @endphp
                    {!! Form::number("payment[0][cash_ring_percentage]", $percentage, ['class' => 'form-control cash-ring-percentage', 'placeholder' => __('Percentage'), 'id' => "cash_ring_percentage_0", 'step' => '0.01', 'min' => '0', 'max' => '100']); !!}
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  {!! Form::label("cash_ring_final_amount_0", __('Final Amount')) !!}
                  <div class="input-group">
                    <span class="input-group-addon">
                      <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::text("payment[0][cash_ring_final_amount]", $payment_line->method == 'cash_ring_percentage' ? @num_format($payment_line->amount) : '', ['class' => 'form-control cash-ring-final-amount', 'readonly' => true, 'id' => "cash_ring_final_amount_0"]); !!}
                  </div>
                </div>
              </div>
            </div>
          </div>

          @for ($i = 1; $i < 4; $i++)
          <div class="payment_details_div @if( $payment_line->method !== 'custom_pay_' . $i ) {{ 'hide' }} @endif" data-type="custom_pay_{{$i}}">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  {!! Form::label("transaction_no_{$i}_0", __('lang_v1.transaction_no')) !!}
                  {!! Form::text("payment[0][transaction_no_{$i}]", $payment_line->transaction_no ?? '', ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no'), 'id' => "transaction_no_{$i}_0"]); !!}
                </div>
              </div>
            </div>
          </div>
          @endfor

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                {!! Form::label("payment[0][note]", __('lang_v1.payment_note') . ':') !!}
                {!! Form::textarea("payment[0][note]", $payment_line->note, ['class' => 'form-control payment-note-small', 'rows' => 2]); !!}
              </div>
            </div>
          </div>
        </div>
      </div>

      <input type="hidden" id="edit_mode" value="1">
      <input type="hidden" id="payment_row_index" value="1">
    </div>

    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">@lang( 'messages.update' )</button>
      <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div></div><style>
.payment_row_block {
  background: #f9f9f9;
  margin-bottom: 15px;
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 5px;
  position: relative;
}

.payment_details_div {
  margin-top: 15px;
  padding: 10px;
  background-color: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 3px;
}

.hide {
  display: none !important;
}

.cash-ring-percentage {
  border: 2px solid #28a745 !important;
  background-color: #f8fff8;
}

.cash-ring-final-amount {
  background-color: #e9ecef !important;
  font-weight: bold;
  color: #495057;
}

.payment-note-small {
  min-height: 50px !important;
  max-height: 80px;
  resize: vertical;
}

.payment-amount-error {
  color: #d73527;
  font-size: 12px;
  margin-top: 5px;
}
</style>

<script>
$(document).ready(function() {
    
    var cachedTransactionTotal = parseFloat($('#transaction_final_total').val()) || 0;
    var isSubmitting = false;
    var isEditMode = $('#edit_mode').val() === '1';
    
    // 泙 1. Function to calculate disabled dates (EXACTLY from payment_row.blade.php)
    function getPaymentDisabledDates() {
        var payment_back_date = parseInt($('#payment_back_date').val()) || 0;
        var disabled_dates = [];
        
        if (payment_back_date > 0) {
            var base_date;
            var original_paid_on = $('#original_paid_on').val();
            
            // If editing, use the original date as the base to preserve it.
            // If creating, use today as the base.
            if (original_paid_on) {
                // Parse using correct format, fallback if needed
                base_date = moment(original_paid_on, 'YYYY-MM-DD HH:mm:ss');
                if(!base_date.isValid()) {
                    base_date = moment(original_paid_on);
                }
            } else {
                base_date = moment();
            }
            
            // Calculate cutoff date: Base Date - Allowed Days
            var cutoff_date = base_date.clone().subtract(payment_back_date, 'days');
            
            // Disable all dates strictly BEFORE the cutoff date
            for (var i = 1; i <= 365; i++) {
                var check_date = base_date.clone().subtract(i, 'days');
                if (check_date.isBefore(cutoff_date, 'day')) {
                    // Format must match datepicker expectation (usually standard US or defined global format)
                    disabled_dates.push(check_date.format('MM/DD/YYYY')); 
                }
            }
        }
        return disabled_dates;
    }

    // 泙 2. Initialize Datepicker with Disabled Dates Logic
    $('.paid_on').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
        disabledDates: getPaymentDisabledDates()
    });

    // Safe JSON parse function
    function safeJsonParse(jsonString, defaultValue = {}) {
        if (!jsonString || jsonString === '' || jsonString === 'null' || jsonString === 'undefined') {
            return defaultValue;
        }
        
        try {
            return JSON.parse(jsonString);
        } catch (e) {
            console.log('Error parsing JSON:', e, 'String:', jsonString);
            return defaultValue;
        }
    }
    
    // CASH RING PERCENTAGE SAVE BUTTON CONTROL
    function checkCashRingAndControlSaveButton() {
        var shouldDisableSave = false;
        
        $('.payment_row_block').each(function() {
            var payment_method = $(this).find('.payment_types_dropdown').val();
            
            if (payment_method === 'cash_ring_percentage') {
                var percentage = $(this).find('.cash-ring-percentage').val();
                
                if (!percentage || percentage === '' || parseFloat(percentage) <= 0) {
                    shouldDisableSave = true;
                }
            }
        });
        
        var saveButton = $('button[type="submit"]');
        
        if (shouldDisableSave) {
            saveButton.prop('disabled', true);
        } else {
            saveButton.prop('disabled', false);
        }
    }
    
    // Calculate cash ring percentage final amount
    function calculateCashRingFinalAmount(payment_row) {
        var percentageInput = payment_row.find('.cash-ring-percentage');
        var finalAmountInput = payment_row.find('.cash-ring-final-amount');
        var amountInput = payment_row.find('.payment_amount');
        
        var percentage = parseFloat(percentageInput.val()) || 0;
        var base_amount = parseFloat(amountInput.val()) || 0;
        
        if (percentage > 0 && base_amount > 0) {
            var final_amount = base_amount + ((percentage * base_amount) / 100);
            finalAmountInput.val(final_amount.toFixed(2));
        } else {
            finalAmountInput.val('');
        }
    }
    
    // Payment amount input handler
    $(document).on('input', '.payment_amount', function() {
        var payment_row = $(this).closest('.payment_row_block');
        var payment_method = payment_row.find('.payment_types_dropdown').val();
        
        if (payment_method === 'cash_ring_percentage') {
            calculateCashRingFinalAmount(payment_row);
        }
    });
    
    // CASH RING PERCENTAGE INPUT HANDLER
    $(document).on('input', '.cash-ring-percentage', function() {
        var payment_row = $(this).closest('.payment_row_block');
        calculateCashRingFinalAmount(payment_row);
        checkCashRingAndControlSaveButton();
    });
    
    // PAYMENT METHOD CHANGE HANDLER
    $(document).on('change', '.payment_types_dropdown', function(e) {
        var payment_row = $(this).closest('.payment_row_block');
        var payment_type = $(this).val();
        
        payment_row.find('.payment_details_div').addClass('hide');
        payment_row.find('.payment_details_div[data-type="' + payment_type + '"]').removeClass('hide');
        
        // Handle cash denomination
        if (payment_row.find('.enable_cash_denomination_for_payment_methods').length) {
            var denominationVal = payment_row.find('.enable_cash_denomination_for_payment_methods').val();
            var denomination_methods = safeJsonParse(denominationVal, []);
            
            if (Array.isArray(denomination_methods) && denomination_methods.includes(payment_type)) {
                payment_row.find('.cash_denomination_div').removeClass('hide');
            } else {
                payment_row.find('.cash_denomination_div').addClass('hide');
            }
        }
        
        if (payment_type === 'cash_ring_percentage') {
            calculateCashRingFinalAmount(payment_row);
        }

        set_default_payment_account();
        checkCashRingAndControlSaveButton();
    });
    
    // Set default payment account function
    function set_default_payment_account() {
        var defaultAccountsVal = $('#default_payment_accounts').val();
        var default_accounts = safeJsonParse(defaultAccountsVal, {});
        
        $('.payment_types_dropdown').each(function() {
            var payment_row = $(this).closest('.payment_row_block');
            var payment_type = $(this).val();
            
            if (payment_type && payment_type !== 'advance') {
                var default_account = '';
                if (default_accounts[payment_type] && default_accounts[payment_type]['account']) {
                    default_account = default_accounts[payment_type]['account'];
                }
                payment_row.find('select[name*="[account_id]"]').val(default_account).trigger('change');
            }
        });
    }
    
    // Initialize existing form elements
    set_default_payment_account();
    
    var firstPaymentRow = $('.payment_row_block[data-row-index="0"]');
    var firstPaymentMethod = firstPaymentRow.find('.payment_types_dropdown').val();
    if (firstPaymentMethod === 'cash_ring_percentage') {
        calculateCashRingFinalAmount(firstPaymentRow);
    }
    
    // Form validation and submission
    $(document).on('submit', 'form#transaction_payment_add_form', function(e){
        e.preventDefault();
        
        if (isSubmitting) {
            return false;
        }
        
        var is_valid = true;
        var payment_row = $('.payment_row_block[data-row-index="0"]');
        var payment_type = payment_row.find('.payment_types_dropdown').val();
        var payment_amount = parseFloat(payment_row.find('.payment_amount').val()) || 0;
        
        if (payment_amount <= 0) {
            alert('Please enter a valid payment amount.');
            return false;
        }
        
        // Validate cash denomination if enabled
        if (payment_row.find('.enable_cash_denomination_for_payment_methods').length) {
            var denominationVal = payment_row.find('.enable_cash_denomination_for_payment_methods').val();
            var denomination_for_payment_types = safeJsonParse(denominationVal, []);
            
            if (Array.isArray(denomination_for_payment_types) && 
                denomination_for_payment_types.includes(payment_type) && 
                payment_row.find('.is_strict').length && 
                payment_row.find('.is_strict').val() === '1') {
                
                var total_denomination = parseFloat(payment_row.find('.denomination_total_amount').val()) || 0;
                
                if (payment_amount !== total_denomination) {
                    is_valid = false;
                    payment_row.find('.cash_denomination_error').removeClass('hide');
                } else {
                    payment_row.find('.cash_denomination_error').addClass('hide');
                }
            }
        }

        if (!is_valid) {
            return false;
        }
        
        // Submit the form directly for edit mode
        submitForm();
    });
    
    function submitForm() {
        if (isSubmitting) return;
        
        isSubmitting = true;
        
        $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
        $('form#transaction_payment_add_form')[0].submit();
    }
    
    // INITIAL CHECK ON PAGE LOAD FOR EDIT
    setTimeout(function() {
        checkCashRingAndControlSaveButton();
    }, 500);
});
</script>