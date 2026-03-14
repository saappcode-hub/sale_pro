<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockCashRingBalanceProduct extends Model
{
    protected $table = 'stock_cash_ring_balance_product';
    public $timestamps = false;
    public $incrementing = false; // No auto-increment behavior
    protected $fillable = ['product_id', 'business_id', 'location_id', 'cash_ring_balance_id', 'stock_cash_ring_balance'];
}
