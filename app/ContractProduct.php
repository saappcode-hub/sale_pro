<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContractProduct extends Model
{
    protected $table = 'contract_products';
    
    // 1. Disable default timestamps so Laravel doesn't look for 'updated_at'
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'product_id',
        'target_quantity',
        'unit_price',
        'discount',
        'discount_type',
        'subtotal',
        'parent_sell_line_id',
        'children_type',
        'sub_unit_id',
        'created_at', // Allow mass assignment for created_at
    ];

    // 2. Only cast created_at to date (remove updated_at)
    protected $dates = ['created_at'];

    // 3. Automatically set created_at when creating a new record
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function contract()
    {
        return $this->belongsTo(CustomerContract::class, 'contract_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}