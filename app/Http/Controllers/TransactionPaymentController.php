<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Events\TransactionPaymentAdded;
use App\Events\TransactionPaymentUpdated;
use App\Exceptions\AdvanceBalanceNotAvailable;
use App\StockCashRingBalanceCustomer;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Datatables;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransactionPaymentController extends Controller
{
    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $transaction_id = $request->input('transaction_id');
            $transaction = Transaction::where('business_id', $business_id)->with(['contact'])->findOrFail($transaction_id);

            $transaction_before = $transaction->replicate();

            if (! (auth()->user()->can('purchase.payments') || auth()->user()->can('sell.payments') || auth()->user()->can('all_expense.access') || auth()->user()->can('view_own_expense'))) {
                abort(403, 'Unauthorized action.');
            }

            if ($transaction->payment_status != 'paid') {
                $payments = $request->input('payment', []);
                
                if (empty($payments)) {
                    throw new \Exception('No payment data provided');
                }

                $formatted_payments = [];
                
                foreach ($payments as $index => $payment_data) {
                    if (empty($payment_data['amount']) || $payment_data['amount'] <= 0) {
                        continue;
                    }

                    // Handle file upload for this payment row BEFORE creating payment
                    $document_path = null;
                    if ($request->hasFile("payment.{$index}.document")) {
                        $document_path = $this->transactionUtil->uploadFile($request, "payment.{$index}.document", 'documents');
                    }

                    $payment = [
                        'amount' => $payment_data['amount'],
                        'method' => $payment_data['method'] ?? 'cash',
                        'note' => $payment_data['note'] ?? '',
                        'paid_on' => $payment_data['paid_on'] ?? now()->format('m/d/Y H:i'),
                        'document' => $document_path,
                    ];

                    // Handle card payments
                    if ($payment['method'] == 'card') {
                        $payment['card_number'] = $payment_data['card_number'] ?? '';
                        $payment['card_holder_name'] = $payment_data['card_holder_name'] ?? '';
                        $payment['card_transaction_number'] = $payment_data['card_transaction_number'] ?? '';
                        $payment['card_type'] = $payment_data['card_type'] ?? '';
                        $payment['card_month'] = $payment_data['card_month'] ?? '';
                        $payment['card_year'] = $payment_data['card_year'] ?? '';
                        $payment['card_security'] = $payment_data['card_security'] ?? '';
                    }

                    // Handle cheque/bank
                    if ($payment['method'] == 'cheque') {
                        $payment['cheque_number'] = $payment_data['cheque_number'] ?? '';
                    }
                    if ($payment['method'] == 'bank_transfer') {
                        $payment['bank_account_number'] = $payment_data['bank_account_number'] ?? '';
                    }

                    // Handle cash ring percentage
                    if ($payment['method'] == 'cash_ring_percentage') {
                        $payment['cash_ring_percentage'] = $payment_data['cash_ring_percentage'] ?? 0;
                        $payment['cash_ring_final_amount'] = $payment_data['cash_ring_final_amount'] ?? 0;
                    }

                    // Handle custom payments
                    for ($i = 1; $i <= 3; $i++) {
                        if ($payment['method'] == "custom_pay_{$i}") {
                            $payment["transaction_no_{$i}"] = $payment_data["transaction_no_{$i}"] ?? '';
                        }
                    }

                    // Handle account assignment
                    if (!empty($payment_data['account_id']) && $payment['method'] != 'advance') {
                        $payment['account_id'] = $payment_data['account_id'];
                    }

                    // Handle denominations
                    if (!empty($payment_data['denominations'])) {
                        $payment['denominations'] = $payment_data['denominations'];
                    }

                    $formatted_payments[] = $payment;
                }

                if (empty($formatted_payments)) {
                    throw new \Exception('No valid payment data provided');
                }

                DB::beginTransaction();

                $this->processMultiplePayments($transaction, $formatted_payments, $business_id);

                // Update payment status
                $payment_status = $this->transactionUtil->updatePaymentStatus($transaction_id, $transaction->final_total);
                $transaction->payment_status = $payment_status;

                $this->transactionUtil->activityLog($transaction, 'payment_edited', $transaction_before);

                DB::commit();

                // Update cache
                $this->quickCacheUpdate($business_id, $transaction);
            }

            $output = [
                'success' => true,
                'msg' => __('purchase.payment_added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            $msg = __('messages.something_went_wrong');

            if (get_class($e) == \App\Exceptions\AdvanceBalanceNotAvailable::class) {
                // NOTE: This triggers if 'Advance' payment is used with insufficient balance
                $msg = $e->getMessage();
            } else {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            }

            $output = [
                'success' => false,
                'msg' => $msg,
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

private function quickCacheUpdate($business_id, $transaction)
    {
        try {
            $sellController = app(SellController::class);
            $sellController->updateCacheAfterStore($transaction);
            
            Log::info("Quick cache update completed for payment on transaction: {$transaction->id}");
        } catch (\Exception $e) {
            Log::error("Quick cache update failed: " . $e->getMessage());
        }
    }

    private function quickCacheDelete($business_id, $transaction_id)
    {
        try {
            // Get all fast cache keys
            $trackingKey = "fast_cache_keys_{$business_id}";
            $fastCacheKeys = Cache::get($trackingKey, []);
            
            // Get old-style cache keys
            $oldCacheKeys = Cache::get("sales_cache_keys_list_{$business_id}", []);
            
            $allCacheKeys = array_merge($fastCacheKeys, $oldCacheKeys);
            
            if (empty($allCacheKeys)) {
                return; // No cache to update
            }
            
            // Remove transaction from each cache entry
            foreach ($allCacheKeys as $cacheKey) {
                $cached = Cache::get($cacheKey);
                
                if (!$cached) {
                    continue; // Cache entry doesn't exist
                }
                
                $updated = false;
                
                // Handle old-style cache structure with 'data' key
                if (isset($cached['data']) && is_array($cached['data'])) {
                    $originalCount = count($cached['data']);
                    $cached['data'] = array_filter($cached['data'], function($item) use ($transaction_id) {
                        return !isset($item['id']) || $item['id'] != $transaction_id;
                    });
                    
                    if (count($cached['data']) < $originalCount) {
                        // Re-index array and update total count
                        $cached['data'] = array_values($cached['data']);
                        $cached['total'] = count($cached['data']);
                        $cached['timestamp'] = time();
                        $updated = true;
                    }
                }
                // Handle new fast cache structure (direct array)
                elseif (is_array($cached)) {
                    $originalCount = count($cached);
                    $cached = array_filter($cached, function($item) use ($transaction_id) {
                        return !isset($item['id']) || $item['id'] != $transaction_id;
                    });
                    
                    if (count($cached) < $originalCount) {
                        // Re-index array
                        $cached = array_values($cached);
                        $updated = true;
                    }
                }
                
                if ($updated) {
                    Cache::put($cacheKey, $cached, 1800);
                }
            }
            
            Log::info("Quick cache delete completed for payment on transaction: {$transaction_id}");
            
        } catch (\Exception $e) {
            Log::error("Quick cache delete failed: " . $e->getMessage());
        }
    }

    /**
     * Process multiple payments following the createOrUpdatePaymentLines pattern
     */
    private function processMultiplePayments($transaction, $payments, $business_id)
    {
        $payments_formatted = [];
        $account_transactions = [];
        $denominations = [];
        $cash_ring_payments = [];
        
        $prefix_type = 'purchase_payment';
        if (in_array($transaction->type, ['sell', 'sell_return'])) {
            $prefix_type = 'sell_payment';
        } elseif (in_array($transaction->type, ['expense', 'expense_refund'])) {
            $prefix_type = 'expense_payment';
        }
        
        $contact_balance = !empty($transaction->contact) ? $transaction->contact->balance : 0;
        $c = 0;

        foreach ($payments as $payment) {
            $payment_amount = $this->transactionUtil->num_uf($payment['amount']);
            
            // Check advance balance (keep this check as it's crucial for the Advance payment method)
            if ($payment['method'] == 'advance' && $payment_amount > $contact_balance) {
                throw new AdvanceBalanceNotAvailable(__('lang_v1.required_advance_balance_not_available'));
            }
            
            if ($payment_amount == 0) continue;

            $ref_count = $this->transactionUtil->setAndGetReferenceCount($prefix_type);
            $payment_ref_no = $this->transactionUtil->generateReferenceNumber($prefix_type, $ref_count);
            $paid_on = $this->transactionUtil->uf_date($payment['paid_on'], true);

            // Handle cash ring logic (using $original_amount_for_balance for base amount)
            $final_payment_amount = $payment_amount;
            $original_amount_for_balance = $payment_amount;
            
            if ($payment['method'] == 'cash_ring_percentage') {
                $percentage = isset($payment['cash_ring_percentage']) ? floatval($payment['cash_ring_percentage']) : 0;
                
                if (isset($payment['cash_ring_final_amount']) && !empty($payment['cash_ring_final_amount'])) {
                    $final_amount = $this->transactionUtil->num_uf($payment['cash_ring_final_amount']);
                } else {
                    $percentage_amount = ($payment_amount * $percentage) / 100;
                    $final_amount = $payment_amount + $percentage_amount;
                }
                
                $final_amount = round($final_amount, 2);
                $final_payment_amount = $final_amount;
                
                $cash_ring_payments[] = [
                    'contact_id' => $transaction->contact_id,
                    'business_id' => $transaction->business_id,
                    'amount' => $original_amount_for_balance,
                    'percentage' => $percentage,
                    'final_amount' => $final_amount,
                    'method' => 'cash_ring_percentage'
                ];
            } elseif ($payment['method'] == 'cash_ring') {
                $cash_ring_payments[] = [
                    'contact_id' => $transaction->contact_id,
                    'business_id' => $transaction->business_id,
                    'amount' => $payment_amount,
                    'method' => 'cash_ring'
                ];
            }

            $payment_data = [
                // Use the calculated final amount for the payment record amount
                'amount' => $final_payment_amount, 
                'method' => $payment['method'],
                'business_id' => $transaction->business_id,
                'is_return' => 0,
                'card_transaction_number' => $payment['card_transaction_number'] ?? null,
                'card_number' => $payment['card_number'] ?? null,
                'card_type' => $payment['card_type'] ?? null,
                'card_holder_name' => $payment['card_holder_name'] ?? null,
                'card_month' => $payment['card_month'] ?? null,
                'card_year' => $payment['card_year'] ?? null,
                'card_security' => $payment['card_security'] ?? null,
                'cheque_number' => $payment['cheque_number'] ?? null,
                'bank_account_number' => $payment['bank_account_number'] ?? null,
                'note' => $payment['note'] ?? null,
                'paid_on' => $paid_on,
                'created_by' => auth()->user()->id,
                'payment_for' => $transaction->contact_id,
                'payment_ref_no' => $payment_ref_no,
                'account_id' => (!empty($payment['account_id']) && $payment['method'] != 'advance') ? $payment['account_id'] : null,
                'transaction_id' => $transaction->id,
                'document' => $payment['document'] ?? null,
            ];

            if ($payment['method'] == 'cash_ring_percentage') {
                $payment_data['cash_ring_percentage'] = round($original_amount_for_balance, 2);
                $payment_data['percentage'] = round($payment['cash_ring_percentage'] ?? 0, 2);
            } elseif ($payment['method'] == 'cash_ring') {
                $payment_data['cash_ring_amount'] = round($payment_amount, 2);
            }

            for ($i = 1; $i <= 3; $i++) {
                if ($payment['method'] == "custom_pay_{$i}") {
                    $payment_data['transaction_no'] = $payment["transaction_no_{$i}"] ?? '';
                }
            }

            // Add to formatted array (Do not save yet)
            $payments_formatted[] = new TransactionPayment($payment_data);

            if (!empty($payment['denominations'])) {
                $denominations[$payment_ref_no] = $payment['denominations'];
            }

            $account_transactions[$c] = $payment_data;
            $account_transactions[$c]['transaction_type'] = $transaction->type;
            $c++;
        }

        if (!empty($payments_formatted)) {
            // 1. Save payments and capture the saved Models (returns an array in some Laravel versions)
            $saved_payments_array = $transaction->payment_lines()->saveMany($payments_formatted);
            
            // 🔑 FIX: Convert the array of saved models to a Collection
            $saved_payments = collect($saved_payments_array); 
            
            // 2. Fire events using the SAVED models collection
            foreach ($account_transactions as $account_transaction) {
                // Find the specific payment model from the saved collection
                $payment = $saved_payments->where('payment_ref_no', $account_transaction['payment_ref_no'])->first();
                
                if (!empty($payment)) {
                    event(new TransactionPaymentAdded($payment, $account_transaction));
                }
            }

            // 3. Process denominations
            if (!empty($denominations)) {
                foreach ($denominations as $key => $value) {
                    $payment = $saved_payments->where('payment_ref_no', $key)->first();
                    if($payment) {
                        $this->transactionUtil->addCashDenominations($payment, $value);
                    }
                }
            }
        }

        if (!empty($cash_ring_payments)) {
            $this->transactionUtil->processCashRingPayments($cash_ring_payments);
        }

        return true;
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! (auth()->user()->can('sell.payments') || auth()->user()->can('purchase.payments'))) {
            abort(403, 'Unauthorized action.');
        }
        
        if (request()->ajax()) {
            $transaction = Transaction::where('id', $id)
                                    ->with(['contact', 'business', 'transaction_for'])
                                    ->first();
            
            $payments_query = TransactionPayment::where('transaction_payments.transaction_id', $id)
                ->leftJoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
                ->leftJoin('transactions_ring_balance as trb', function($join) {
                    $join->on('t.invoice_no', '=', 'trb.sell_ref_invoice')
                        ->where('trb.business_id', '=', auth()->user()->business_id)
                        ->whereNull('trb.deleted_at'); // Condition added here
                })
                ->select('transaction_payments.*', 'trb.invoice_no as ring_top_up_ref');
            
            $accounts_enabled = false;
            if ($this->moduleUtil->isModuleEnabled('account')) {
                $accounts_enabled = true;
                $payments_query->with(['payment_account']);
            }
            
            $payments = $payments_query->get();
            
            $location_id = ! empty($transaction->location_id) ? $transaction->location_id : null;
            $payment_types = $this->transactionUtil->payment_types($location_id, true);
            
            return view('transaction_payment.show_payments')
                    ->with(compact('transaction', 'payments', 'payment_types', 'accounts_enabled'));
        }
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
  public function edit($id)
{
    if (! auth()->user()->can('edit_purchase_payment') && ! auth()->user()->can('edit_sell_payment')) {
        abort(403, 'Unauthorized action.');
    }

    if (request()->ajax()) {
        $business_id = request()->session()->get('user.business_id');
        
        $payment_line = TransactionPayment::with(['denominations'])
                            ->where('method', '!=', 'advance')
                            ->findOrFail($id);
        
        // Fetch transaction and business to get settings
        $transaction = Transaction::where('id', $payment_line->transaction_id)
            ->where('business_id', $business_id)
            ->with(['contact', 'location', 'business']) // Must include 'business'
            ->first();

        if (empty($transaction)) {
            abort(404, 'Transaction not found for the payment.');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location);
        $accounts = $this->moduleUtil->accountsDropdown($business_id, true, false, true);
        $amount_formated = $this->transactionUtil->num_f($payment_line->amount);
        
        // 🔑 NEW/CONFIRMED: Payment Back Date Logic
        // This variable is 0 (disabled) or 1 (enabled).
        $payment_back_date = $transaction->business->payment_back_date ?? 0;
        
        // The original paid date is needed to allow editing a payment that was originally back-dated, 
        // even if the setting is now disabled.
        $original_paid_on = $payment_line->paid_on;

        return view('transaction_payment.edit_payment_row')
            ->with(compact(
                'transaction', 
                'payment_types', 
                'payment_line', 
                'accounts', 
                'amount_formated',
                'payment_back_date',
                'original_paid_on'
            ));
    }
}

    // In TransactionPaymentController.php - Update the update method

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('edit_purchase_payment') && ! auth()->user()->can('edit_sell_payment') && ! auth()->user()->can('all_expense.access') && ! auth()->user()->can('view_own_expense')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            // Get the payment to update
            $payment = TransactionPayment::where('method', '!=', 'advance')->findOrFail($id);
            
            $transaction = Transaction::where('business_id', $business_id)
                                ->find($payment->transaction_id);

            $transaction_before = $transaction->replicate();

            // Handle multi-payment update with cash ring support
            if ($request->has('payment')) {
                // Get payment array from request
                $payments = $request->input('payment', []);
                
                if (empty($payments)) {
                    throw new \Exception('No payment data provided');
                }

                // Find the specific payment data (should be index 0 for edit)
                $payment_data = isset($payments[0]) ? $payments[0] : null;
                
                if (!$payment_data || empty($payment_data['amount']) || $payment_data['amount'] <= 0) {
                    throw new \Exception('Invalid payment data provided');
                }

                DB::beginTransaction();

                // Check if this is a cash ring payment that needs reversal
                $old_method = $payment->method;
                $old_amount = $payment->amount;
                $old_cash_ring_percentage = $payment->cash_ring_percentage ?? null;
                $new_method = $payment_data['method'] ?? 'cash';

                // Perform cash ring reversal if needed
                if (in_array($old_method, ['cash_ring', 'cash_ring_percentage']) && $transaction->contact_id) {
                    $this->reverseCashRingBalance($transaction->contact_id, $transaction->business_id, $old_amount, $old_cash_ring_percentage, $old_method);
                }

                // Handle document upload
                $document_path = null;
                if ($request->hasFile("payment.0.document")) {
                    $document_path = $this->transactionUtil->uploadFile($request, "payment.0.document", 'documents');
                }

                // Get the base amount (before any percentage calculation)
                $base_amount = $this->transactionUtil->num_uf($payment_data['amount']);
                $final_amount = $base_amount; // Default to base amount

                $inputs = [
                    'method' => $new_method,
                    'note' => $payment_data['note'] ?? '',
                    'paid_on' => $this->transactionUtil->uf_date($payment_data['paid_on'] ?? now()->format('m/d/Y H:i'), true),
                ];

                // Add document if uploaded
                if ($document_path) {
                    $inputs['document'] = $document_path;
                }

                // Handle cash ring percentage with proper calculation
                if ($inputs['method'] == 'cash_ring_percentage') {
                    $percentage = floatval($payment_data['cash_ring_percentage'] ?? 0);
                    
                    // Use the final amount directly from the request if provided
                    if (isset($payment_data['cash_ring_final_amount']) && !empty($payment_data['cash_ring_final_amount'])) {
                        $final_amount = $this->transactionUtil->num_uf($payment_data['cash_ring_final_amount']);
                    } else {
                        // Calculate final amount with proper precision
                        $percentage_amount = ($base_amount * $percentage) / 100;
                        $final_amount = $base_amount + $percentage_amount;
                    }
                    
                    // Round to 4 decimal places to match database precision
                    $final_amount = round($final_amount, 4);
                    
                    // Store values with proper precision
                    $inputs['amount'] = $final_amount;
                    $inputs['cash_ring_percentage'] = round($base_amount, 2); // Original amount
                    $inputs['percentage'] = round($percentage, 2); // Percentage value
                }
                // Handle cash ring (regular)
                elseif ($inputs['method'] == 'cash_ring') {
                    $inputs['amount'] = round($base_amount, 4);
                    $inputs['cash_ring_amount'] = round($base_amount, 2);
                    // Clear percentage fields
                    $inputs['cash_ring_percentage'] = null;
                    $inputs['percentage'] = null;
                }
                // Handle other payment methods
                else {
                    $inputs['amount'] = round($base_amount, 4);
                    // Clear cash ring fields
                    $inputs['cash_ring_percentage'] = null;
                    $inputs['percentage'] = null;
                }

                // Handle card payments
                if ($inputs['method'] == 'card') {
                    $inputs['card_number'] = $payment_data['card_number'] ?? '';
                    $inputs['card_holder_name'] = $payment_data['card_holder_name'] ?? '';
                    $inputs['card_transaction_number'] = $payment_data['card_transaction_number'] ?? '';
                    $inputs['card_type'] = $payment_data['card_type'] ?? '';
                    $inputs['card_month'] = $payment_data['card_month'] ?? '';
                    $inputs['card_year'] = $payment_data['card_year'] ?? '';
                    $inputs['card_security'] = $payment_data['card_security'] ?? '';
                }

                // Handle cheque payments
                if ($inputs['method'] == 'cheque') {
                    $inputs['cheque_number'] = $payment_data['cheque_number'] ?? '';
                }

                // Handle bank transfer payments
                if ($inputs['method'] == 'bank_transfer') {
                    $inputs['bank_account_number'] = $payment_data['bank_account_number'] ?? '';
                }

                // Handle custom payment methods
                for ($i = 1; $i <= 3; $i++) {
                    if ($inputs['method'] == "custom_pay_{$i}") {
                        $inputs['transaction_no'] = $payment_data["transaction_no_{$i}"] ?? '';
                    }
                }

                // Handle account assignment
                if (!empty($payment_data['account_id']) && $inputs['method'] != 'advance') {
                    $inputs['account_id'] = $payment_data['account_id'];
                }

                // Handle cash denominations if provided
                if (!empty($payment_data['denominations'])) {
                    $this->transactionUtil->updateCashDenominations($payment, $payment_data['denominations']);
                }

                //Update parent payment if exists
                if (! empty($payment->parent_id)) {
                    $parent_payment = TransactionPayment::find($payment->parent_id);
                    $parent_payment->amount = $parent_payment->amount - ($payment->amount - $inputs['amount']);
                    $parent_payment->save();
                }

                // Update the payment record
                $payment->update($inputs);

                // Process new cash ring payment if needed
                if (in_array($new_method, ['cash_ring', 'cash_ring_percentage']) && $transaction->contact_id) {
                    $this->processNewCashRingPayment($transaction, $payment, $inputs, $base_amount);
                }

                //update payment status
                $payment_status = $this->transactionUtil->updatePaymentStatus($payment->transaction_id);
                $transaction->payment_status = $payment_status;

                $this->transactionUtil->activityLog($transaction, 'payment_edited', $transaction_before);

                DB::commit();
                // Update cache after payment updated
               $this->quickCacheUpdate($business_id, $transaction);
                //event
                event(new TransactionPaymentUpdated($payment, $transaction->type));

                $output = ['success' => true,
                    'msg' => __('purchase.payment_updated_success'),
                ];
            }
            // Handle single payment update (existing functionality)
            else {
                // ... existing single payment update code ...
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Reverse cash ring balance for old payment
     */
    private function reverseCashRingBalance($contact_id, $business_id, $old_amount, $old_cash_ring_percentage, $old_method)
    {
        try {
            $stock_balance = StockCashRingBalanceCustomer::where('contact_id', $contact_id)
                ->where('business_id', $business_id)
                ->first();
            
            if ($stock_balance) {
                $current_dollar = $stock_balance->total_cuurency_dollar ?? 0;
                
                // Determine amount to reverse based on old method
                $amount_to_reverse = 0;
                if ($old_method == 'cash_ring_percentage' && $old_cash_ring_percentage) {
                    // For percentage method, reverse the original amount (stored in cash_ring_percentage column)
                    $amount_to_reverse = $old_cash_ring_percentage;
                } else {
                    // For regular cash ring, reverse the payment amount
                    $amount_to_reverse = $old_amount;
                }
                
                // Reverse the previous deduction (add back the amount)
                $new_total_balance = $current_dollar + $amount_to_reverse;
                
                $stock_balance->total_cuurency_dollar = $new_total_balance;
                $stock_balance->save();
                
                \Log::info('Reversed Cash Ring Balance:', [
                    'contact_id' => $contact_id,
                    'business_id' => $business_id,
                    'previous_balance' => $current_dollar,
                    'amount_reversed' => $amount_to_reverse,
                    'new_balance' => $new_total_balance,
                    'old_method' => $old_method
                ]);
            }
        } catch (\Exception $e) {
            \Log::emergency('Error reversing cash ring balance: ' . $e->getMessage());
        }
    }

    /**
     * Process new cash ring payment
     */
    private function processNewCashRingPayment($transaction, $payment, $inputs, $base_amount)
    {
        $cash_ring_payments = [];
        
        if ($inputs['method'] == 'cash_ring_percentage') {
            $cash_ring_payments[] = [
                'contact_id' => $transaction->contact_id,
                'business_id' => $transaction->business_id,
                'amount' => $base_amount, // Use base amount for balance calculation
                'percentage' => $inputs['percentage'] ?? 0,
                'final_amount' => $inputs['amount'],
                'method' => 'cash_ring_percentage'
            ];
        } elseif ($inputs['method'] == 'cash_ring') {
            $cash_ring_payments[] = [
                'contact_id' => $transaction->contact_id,
                'business_id' => $transaction->business_id,
                'amount' => $base_amount,
                'method' => 'cash_ring'
            ];
        }
        
        if (!empty($cash_ring_payments)) {
            $this->transactionUtil->processCashRingPayments($cash_ring_payments);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
{
    if (! auth()->user()->can('delete_purchase_payment') && ! auth()->user()->can('delete_sell_payment') && ! auth()->user()->can('all_expense.access') && ! auth()->user()->can('view_own_expense')) {
        abort(403, 'Unauthorized action.');
    }
   
    if (request()->ajax()) {
        try {
            $payment = TransactionPayment::findOrFail($id);
            
            // GET TRANSACTION BEFORE DELETION FOR CACHE UPDATE
            $transaction = null;
            $business_id = request()->session()->get('user.business_id');
            if (!empty($payment->transaction_id)) {
                $transaction = Transaction::find($payment->transaction_id);
            }
            DB::beginTransaction();
           
            // Handle cash ring balance reversal before deletion
            if (in_array($payment->method, ['cash_ring', 'cash_ring_percentage'])) {
                $this->reverseCashRingBalanceBeforeDelete($payment);
            }
           
            if (! empty($payment->transaction_id)) {
                TransactionPayment::deletePayment($payment);
            } else { //advance payment
                $adjusted_payments = TransactionPayment::where('parent_id', $payment->id)->get();
                $total_adjusted_amount = $adjusted_payments->sum('amount');
               
                //Get customer advance share from payment and deduct from advance balance
                $total_customer_advance = $payment->amount - $total_adjusted_amount;
                if ($total_customer_advance > 0) {
                    $this->transactionUtil->updateContactBalance($payment->payment_for, $total_customer_advance, 'deduct');
                }
               
                //Delete all child payments (check for cash ring in children too)
                foreach ($adjusted_payments as $adjusted_payment) {
                    // Handle cash ring reversal for child payments
                    if (in_array($adjusted_payment->method, ['cash_ring', 'cash_ring_percentage'])) {
                        $this->reverseCashRingBalanceBeforeDelete($adjusted_payment);
                    }
                   
                    //Make parent payment null as it will get deleted
                    $adjusted_payment->parent_id = null;
                    TransactionPayment::deletePayment($adjusted_payment);
                }
               
                //Delete advance payment
                TransactionPayment::deletePayment($payment);
            }
           
            DB::commit();
            
            // UPDATE CACHE AFTER SUCCESSFUL DELETION
            if ($transaction && $transaction->type == 'sell') {
                $this->quickCacheUpdate($business_id, $transaction);
            } else {
                // For advance payments, use simple cache refresh
                $this->quickCacheDelete($business_id, $payment->id);
            }
           
            $output = ['success' => true, 'msg' => __('purchase.payment_deleted_success')];
           
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }
       
        return $output;
    }
}

    /**
     * Reverse cash ring balance before deleting payment
     */
    private function reverseCashRingBalanceBeforeDelete($payment)
    {
        try {
            // Get transaction to access contact_id and business_id
            $transaction = Transaction::find($payment->transaction_id);
            
            if (!$transaction || !$transaction->contact_id) {
                \Log::warning("Cannot reverse cash ring balance - no transaction or contact found", [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id
                ]);
                return;
            }

            // Find existing cash ring balance record
            $cash_ring_balance = StockCashRingBalanceCustomer::where('contact_id', $transaction->contact_id)
                ->where('business_id', $transaction->business_id)
                ->first();

            if (!$cash_ring_balance) {
                \Log::warning("No cash ring balance record found for reversal", [
                    'contact_id' => $transaction->contact_id,
                    'business_id' => $transaction->business_id,
                    'payment_id' => $payment->id
                ]);
                return;
            }

            // Calculate the amount to reverse based on payment method
            $reversal_amount = 0;
            
            if ($payment->method === 'cash_ring_percentage') {
                // For percentage method, reverse the base amount (cash_ring_percentage field)
                $reversal_amount = floatval($payment->cash_ring_percentage ?? 0);
            } else if ($payment->method === 'cash_ring') {
                // For regular cash ring, reverse the full amount
                $reversal_amount = floatval($payment->amount);
            }

            if ($reversal_amount > 0) {
                // Add back the reversed amount to total_currency_dollar
                $old_balance = $cash_ring_balance->total_cuurency_dollar;
                $cash_ring_balance->total_cuurency_dollar += $reversal_amount;
                $cash_ring_balance->save();

                \Log::info("Cash ring balance reversed before deletion", [
                    'payment_id' => $payment->id,
                    'contact_id' => $transaction->contact_id,
                    'business_id' => $transaction->business_id,
                    'method' => $payment->method,
                    'reversal_amount' => $reversal_amount,
                    'old_balance' => $old_balance,
                    'new_balance' => $cash_ring_balance->total_cuurency_dollar
                ]);
            } else {
                \Log::warning("No amount to reverse for cash ring payment", [
                    'payment_id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'cash_ring_percentage' => $payment->cash_ring_percentage
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error("Error reversing cash ring balance before deletion: " . $e->getMessage(), [
                'payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Adds new payment to the given transaction.
     *
     * @param  int  $transaction_id
     * @return \Illuminate\Http\Response
     */
   public function addPayment($transaction_id)
{
    if (! auth()->user()->can('purchase.payments') && ! auth()->user()->can('sell.payments') && ! auth()->user()->can('all_expense.access') && ! auth()->user()->can('view_own_expense')) {
        abort(403, 'Unauthorized action.');
    }

    if (request()->ajax()) {
        $business_id = request()->session()->get('user.business_id');

        $transaction = Transaction::where('business_id', $business_id)
                                    ->with(['contact', 'location', 'business'])  // ADD 'business'
                                    ->findOrFail($transaction_id);
        
        if ($transaction->payment_status != 'paid') {
            $show_advance = in_array($transaction->type, ['sell', 'purchase']) ? true : false;
            $payment_types = $this->transactionUtil->payment_types($transaction->location, $show_advance);

            $paid_amount = $this->transactionUtil->getTotalPaid($transaction_id);
            $amount = $transaction->final_total - $paid_amount;

            // Add proper rounding to avoid floating point issues
            $amount = round($amount, 2);

            if ($amount < 0.01) {
                $amount = 0;
            }

            $amount_formated = $this->transactionUtil->num_f($amount);

            $payment_line = new TransactionPayment();
            $payment_line->amount = $amount;
            $payment_line->method = 'cash';
            $payment_line->paid_on = \Carbon::now()->toDateTimeString();

            //Accounts
            $accounts = $this->moduleUtil->accountsDropdown($business_id, true, false, true);

            // ADD THESE TWO LINES
            $payment_back_date = $transaction->business->payment_back_date ?? 0;
            $original_paid_on = null;  // CREATE mode - no original date

            $view = view('transaction_payment.payment_row')
            ->with(compact('transaction', 'payment_types', 'payment_line', 'amount_formated', 'accounts', 
                          'payment_back_date', 'original_paid_on'))->render();

            $output = ['status' => 'due',
                'view' => $view, ];
        } else {
            $output = ['status' => 'paid',
                'view' => '',
                'msg' => __('purchase.amount_already_paid'),  ];
        }

        return json_encode($output);
    }
}

    /**
     * Shows contact's payment due modal
     *
     * @param  int  $contact_id
     * @return \Illuminate\Http\Response
     */
    public function getPayContactDue($contact_id)
    {
        if (! (auth()->user()->can('sell.payments') || auth()->user()->can('purchase.payments'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $due_payment_type = request()->input('type');
            $query = Contact::where('contacts.id', $contact_id)
                            ->leftjoin('transactions AS t', 'contacts.id', '=', 't.contact_id');
            if ($due_payment_type == 'purchase') {
                $query->select(
                    DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                    DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as total_paid"),
                    'contacts.name',
                    'contacts.supplier_business_name',
                    'contacts.id as contact_id'
                    );
            } elseif ($due_payment_type == 'purchase_return') {
                $query->select(
                    DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                    DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as total_return_paid"),
                    'contacts.name',
                    'contacts.supplier_business_name',
                    'contacts.id as contact_id'
                    );
            } elseif ($due_payment_type == 'sell') {
                $query->select(
                    DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                    DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as total_paid"),
                    'contacts.name',
                    'contacts.supplier_business_name',
                    'contacts.id as contact_id'
                );
            } elseif ($due_payment_type == 'sell_return') {
                $query->select(
                    DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                    DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as total_return_paid"),
                    'contacts.name',
                    'contacts.supplier_business_name',
                    'contacts.id as contact_id'
                    );
            }

            //Query for opening balance details
            $query->addSelect(
                DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid")
            );
            $contact_details = $query->first();

            $payment_line = new TransactionPayment();
            if ($due_payment_type == 'purchase') {
                $contact_details->total_purchase = empty($contact_details->total_purchase) ? 0 : $contact_details->total_purchase;
                $payment_line->amount = $contact_details->total_purchase -
                                    $contact_details->total_paid;
            } elseif ($due_payment_type == 'purchase_return') {
                $payment_line->amount = $contact_details->total_purchase_return -
                                    $contact_details->total_return_paid;
            } elseif ($due_payment_type == 'sell') {
                $contact_details->total_invoice = empty($contact_details->total_invoice) ? 0 : $contact_details->total_invoice;

                $payment_line->amount = $contact_details->total_invoice -
                                    $contact_details->total_paid;
            } elseif ($due_payment_type == 'sell_return') {
                $payment_line->amount = $contact_details->total_sell_return -
                                    $contact_details->total_return_paid;
            }

            //If opening balance due exists add to payment amount
            $contact_details->opening_balance = ! empty($contact_details->opening_balance) ? $contact_details->opening_balance : 0;
            $contact_details->opening_balance_paid = ! empty($contact_details->opening_balance_paid) ? $contact_details->opening_balance_paid : 0;
            $ob_due = $contact_details->opening_balance - $contact_details->opening_balance_paid;
            if ($ob_due > 0) {
                $payment_line->amount += $ob_due;
            }

            $amount_formated = $this->transactionUtil->num_f($payment_line->amount);

            $contact_details->total_paid = empty($contact_details->total_paid) ? 0 : $contact_details->total_paid;

            $payment_line->method = 'cash';
            $payment_line->paid_on = \Carbon::now()->toDateTimeString();

            $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);

            //Accounts
            $accounts = $this->moduleUtil->accountsDropdown($business_id, true);

            return view('transaction_payment.pay_supplier_due_modal')
                        ->with(compact('contact_details', 'payment_types', 'payment_line', 'due_payment_type', 'ob_due', 'amount_formated', 'accounts'));
        }
    }

    /**
     * Adds Payments for Contact due
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postPayContactDue(Request $request)
    {
        if (! (auth()->user()->can('sell.payments') || auth()->user()->can('purchase.payments'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $business_id = request()->session()->get('business.id');
            $tp = $this->transactionUtil->payContact($request);

            $pos_settings = ! empty(session()->get('business.pos_settings')) ? json_decode(session()->get('business.pos_settings'), true) : [];
            $enable_cash_denomination_for_payment_methods = ! empty($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : [];
            //add cash denomination
            if (in_array($tp->method, $enable_cash_denomination_for_payment_methods) && ! empty($request->input('denominations')) && ! empty($pos_settings['enable_cash_denomination_on']) && $pos_settings['enable_cash_denomination_on'] == 'all_screens') {
                $denominations = [];

                foreach ($request->input('denominations') as $key => $value) {
                    if (! empty($value)) {
                        $denominations[] = [
                            'business_id' => $business_id,
                            'amount' => $key,
                            'total_count' => $value,
                        ];
                    }
                }

                if (! empty($denominations)) {
                    $tp->denominations()->createMany($denominations);
                }
            }

            DB::commit();
            $output = ['success' => true,
                'msg' => __('purchase.payment_added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * view details of single..,
     * payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function viewPayment($payment_id)
    {
        if (! (auth()->user()->can('sell.payments') ||
                auth()->user()->can('purchase.payments') ||
                auth()->user()->can('edit_sell_payment') ||
                auth()->user()->can('delete_sell_payment') ||
                auth()->user()->can('edit_purchase_payment') ||
                auth()->user()->can('delete_purchase_payment')
            )) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('business.id');
            $single_payment_line = TransactionPayment::findOrFail($payment_id);

            $transaction = null;
            if (! empty($single_payment_line->transaction_id)) {
                $transaction = Transaction::where('id', $single_payment_line->transaction_id)
                                ->with(['contact', 'location', 'transaction_for'])
                                ->first();
            } else {
                $child_payment = TransactionPayment::where('business_id', $business_id)
                        ->where('parent_id', $payment_id)
                        ->with(['transaction', 'transaction.contact', 'transaction.location', 'transaction.transaction_for'])
                        ->first();
                $transaction = ! empty($child_payment) ? $child_payment->transaction : null;
            }

            $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);

            return view('transaction_payment.single_payment_view')
                    ->with(compact('single_payment_line', 'transaction', 'payment_types'));
        }
    }

    /**
     * Retrieves all the child payments of a parent payments
     * payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function showChildPayments($payment_id)
    {
        if (! (auth()->user()->can('sell.payments') ||
                auth()->user()->can('purchase.payments') ||
                auth()->user()->can('edit_sell_payment') ||
                auth()->user()->can('delete_sell_payment') ||
                auth()->user()->can('edit_purchase_payment') ||
                auth()->user()->can('delete_purchase_payment')
            )) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('business.id');

            $child_payments = TransactionPayment::where('business_id', $business_id)
                                                    ->where('parent_id', $payment_id)
                                                    ->with(['transaction', 'transaction.contact'])
                                                    ->get();

            $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);

            return view('transaction_payment.show_child_payments')
                    ->with(compact('child_payments', 'payment_types'));
        }
    }
private function updateSellCacheAfterPayment($transaction)
{
    try {
        $business_id = $transaction->business_id;
        
        // Get SellController instance and call its cache method
        $sellController = app(SellController::class);
        $sellController->clearCacheForExistingRecord($business_id, $transaction->id);
        
        Log::info("Cache cleared for payment change on transaction: {$transaction->id}");
    } catch (\Exception $e) {
        Log::error("Payment cache update failed: " . $e->getMessage());
    }
}


    /**
     * Retrieves list of all opening balance payments.
     *
     * @param  int  $contact_id
     * @return \Illuminate\Http\Response
     */
    public function getOpeningBalancePayments($contact_id)
    {
        if (! (auth()->user()->can('sell.payments') ||
                auth()->user()->can('purchase.payments') ||
                auth()->user()->can('edit_sell_payment') ||
                auth()->user()->can('delete_sell_payment') ||
                auth()->user()->can('edit_purchase_payment') ||
                auth()->user()->can('delete_purchase_payment')
            )) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('business.id');
        if (request()->ajax()) {
            $query = TransactionPayment::leftjoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'opening_balance')
                ->where('t.contact_id', $contact_id)
                ->where('transaction_payments.business_id', $business_id)
                ->select(
                    'transaction_payments.amount',
                    'method',
                    'paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.document',
                    'transaction_payments.id',
                    'cheque_number',
                    'card_transaction_number',
                    'bank_account_number'
                )
                ->groupBy('transaction_payments.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            return Datatables::of($query)
                ->editColumn('paid_on', '{{@format_datetime($paid_on)}}')
                ->editColumn('method', function ($row) {
                    $method = __('lang_v1.'.$row->method);
                    if ($row->method == 'cheque') {
                        $method .= '<br>('.__('lang_v1.cheque_no').': '.$row->cheque_number.')';
                    } elseif ($row->method == 'card') {
                        $method .= '<br>('.__('lang_v1.card_transaction_no').': '.$row->card_transaction_number.')';
                    } elseif ($row->method == 'bank_transfer') {
                        $method .= '<br>('.__('lang_v1.bank_account_no').': '.$row->bank_account_number.')';
                    } elseif ($row->method == 'custom_pay_1') {
                        $method = __('lang_v1.custom_payment_1').'<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_2') {
                        $method = __('lang_v1.custom_payment_2').'<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_3') {
                        $method = __('lang_v1.custom_payment_3').'<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    }

                    return $method;
                })
                ->editColumn('amount', function ($row) {
                    return '<span class="display_currency paid-amount" data-orig-value="'.$row->amount.'" data-currency_symbol = true>'.$row->amount.'</span>';
                })
                ->addColumn('action', '<button type="button" class="btn btn-primary btn-xs view_payment" data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'viewPayment\'], [$id]) }}"><i class="fas fa-eye"></i> @lang("messages.view")
                    </button> <button type="button" class="btn btn-info btn-xs edit_payment" 
                    data-href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'edit\'], [$id]) }}"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                    &nbsp; <button type="button" class="btn btn-danger btn-xs delete_payment" 
                    data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'destroy\'], [$id]) }}"
                    ><i class="fa fa-trash" aria-hidden="true"></i> @lang("messages.delete")</button> @if(!empty($document))<a href="{{asset("/uploads/documents/" . $document)}}" class="btn btn-success btn-xs" download=""><i class="fa fa-download"></i> @lang("purchase.download_document")</a>@endif')
                ->rawColumns(['amount', 'method', 'action'])
                ->make(true);
        }
    }
}
