<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionSellRingBalance extends Model
{
    // Specifying the table name is optional if it follows Laravel's naming convention
    protected $table = 'transaction_sell_ring_balance';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function ringUnits()
    {
        return $this->hasMany(TransactionSellRingBalanceRingUnits::class, 'transaction_sell_ring_balance_id');
    }

    public function cashRings()
    {
        return $this->hasMany(TransactionCashRingBalance::class);
    }
}
