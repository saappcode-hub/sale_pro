<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class StockRewardsExchange extends Model
{
    protected $table = 'stock_reward_exchange_new';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'transaction_id', 'type', 'contact_id', 'product_id', 'variation_id',
        'quantity', 'new_quantity', 'created_at', 'updated_at'
    ];
}