<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSellRingBalanceRingUnits extends Model
{
    protected $table = 'transaction_sell_ring_balance_ring_units';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];

    public function transactionSellRingBalance()
    {
        return $this->belongsTo(TransactionSellRingBalance::class, 'transaction_sell_ring_balance_id');
    }

    public function ringUnit()
    {
        return $this->belongsTo(RingUnit::class, 'ring_units_id');
    }
}
