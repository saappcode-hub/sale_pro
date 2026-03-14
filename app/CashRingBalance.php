<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashRingBalance extends Model
{
    protected $table = 'cash_ring_balance';
    
    // Enable timestamps if your table has created_at and updated_at columns
    // Set to false only if these columns don't exist
    public $timestamps = true;

    // Remove guarded to avoid conflicts with fillable
    // protected $guarded = ['id'];

    protected $fillable = [
        'business_id', 
        'brand_id',
        'product_id', 
        'type_currency',
        'unit_value',
        'redemption_value',
        'created_by'
        // removed created_at and updated_at from fillable since timestamps handles them
    ];

    // Cast numeric fields to proper types
    protected $casts = [
        'business_id' => 'integer',
        'brand_id' => 'integer', 
        'product_id' => 'integer',
        'type_currency' => 'integer',
        'unit_value' => 'decimal:2',
        'redemption_value' => 'decimal:2',
        'created_by' => 'integer',
    ];

    /**
     * Get the brand associated with the cash ring balance.
     */
    public function brand()
    {
        return $this->belongsTo(Brands::class, 'brand_id');
    }

    /**
     * Get the product associated with the cash ring balance.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the user who created this record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}