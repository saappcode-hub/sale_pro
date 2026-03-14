<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCashRingBalance extends Model
{
    protected $table = 'transaction_cash_ring_balance';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'transaction_sell_ring_balance_id', 'product_id', 'cash_ring_balance_id', 'quantity', 'new_qty', 'transaction_date'
    ];

    public function transactionSellRingBalance()
    {
        return $this->belongsTo(TransactionSellRingBalance::class, 'transaction_sell_ring_balance_id');
    }

    public function cashRingBalance()
    {
        return $this->belongsTo(CashRingBalance::class, 'cash_ring_balance_id');
    }
}