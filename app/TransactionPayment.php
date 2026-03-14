<?php

namespace App;

use App\Events\TransactionPaymentDeleted;
use App\Events\TransactionPaymentUpdated;
use Illuminate\Database\Eloquent\Model;

class TransactionPayment extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the phone record associated with the user.
     */
    public function payment_account()
    {
        return $this->belongsTo(\App\Account::class, 'account_id');
    }

    /**
     * Get the transaction related to this payment.
     */
    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    /**
     * Get the user.
     */
    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get child payments
     */
    public function child_payments()
    {
        return $this->hasMany(\App\TransactionPayment::class, 'parent_id');
    }

    /**
     * Retrieves documents path if exists
     */
    public function getDocumentPathAttribute()
    {
        $path = ! empty($this->document) ? asset('/uploads/documents/'.$this->document) : null;

        return $path;
    }

    /**
     * Removes timestamp from document name
     */
    public function getDocumentNameAttribute()
    {
        $document_name = ! empty(explode('_', $this->document, 2)[1]) ? explode('_', $this->document, 2)[1] : $this->document;

        return $document_name;
    }

    public static function deletePayment($payment)
    {
        // Get transaction util instance
        $transactionUtil = new \App\Utils\TransactionUtil();
        
        //Update parent payment if exists
        if (! empty($payment->parent_id)) {
            $parent_payment = TransactionPayment::find($payment->parent_id);
            $parent_payment->amount -= $payment->amount;
            if ($parent_payment->amount <= 0) {
                $parent_payment->delete();
                event(new TransactionPaymentDeleted($parent_payment));
            } else {
                $parent_payment->save();
                //Add event to update parent payment account transaction
                event(new TransactionPaymentUpdated($parent_payment, null));
            }
        }
        
        // Store payment data for logging before deletion
        $payment_data = [
            'id' => $payment->id,
            'method' => $payment->method,
            'amount' => $payment->amount,
            'cash_ring_percentage' => $payment->cash_ring_percentage ?? null,
            'transaction_id' => $payment->transaction_id
        ];
        
        $payment->delete();
        
        if (! empty($payment->transaction_id)) {
            //update payment status
            $transaction = Transaction::find($payment->transaction_id);
            if ($transaction) {
                $transaction_before = $transaction->replicate();
                $payment_status = $transactionUtil->updatePaymentStatus($payment->transaction_id);
                $transaction->payment_status = $payment_status;
                $transactionUtil->activityLog($transaction, 'payment_edited', $transaction_before);
            }
        }
        
        $log_properities = [
            'id' => $payment_data['id'],
            'ref_no' => $payment->payment_ref_no ?? '',
            'method' => $payment_data['method'],
            'amount' => $payment_data['amount']
        ];
        
        $transactionUtil->activityLog($payment, 'payment_deleted', null, $log_properities);
        
        //Add event to delete account transaction
        event(new TransactionPaymentDeleted($payment));
    }

    public function denominations()
    {
        return $this->morphMany(\App\CashDenomination::class, 'model');
    }
}
