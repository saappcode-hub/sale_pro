<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RewardsExchange extends Model
{
    protected $table = 'rewards_exchange';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'business_id', 'product_for_sale', 'exchange_product', 'exchange_quantity',
        'amount', 'receive_product', 'receive_quantity', 'type', 'deleted_at', 
        'created_by', 'created_at', 'updated_at'
    ];
    public function product() {
        return $this->belongsTo(Product::class, 'exchange_product');
    }
}
