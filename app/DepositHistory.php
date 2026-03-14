<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositHistory extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_history';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'transaction_payment_ids' => 'array',
        'deposit_datetime' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the business that owns the deposit.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    /**
     * Get the bank account associated with the deposit.
     */
    public function bank_account()
    {
        return $this->belongsTo(\App\Account::class, 'bank_account_id');
    }

    /**
     * Get the user who created this deposit.
     */
    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get transaction payments associated with this deposit.
     */
    public function getTransactionPayments()
    {
        if (empty($this->transaction_payment_ids)) {
            return collect([]);
        }

        return TransactionPayment::whereIn('id', $this->transaction_payment_ids)->get();
    }

    /**
     * Get media files associated with this deposit.
     */
    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }
}