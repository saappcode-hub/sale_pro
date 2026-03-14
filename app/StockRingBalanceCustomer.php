<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockRingBalanceCustomer extends Model
{
    protected $table = 'stock_ring_balance_customer';
    public $timestamps = false;
    public $incrementing = false; // No auto-increment behavior
    protected $fillable = ['product_id', 'contact_id', 'business_id', 'stock_ring_balance'];

    // Define relationships (if necessary)
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
