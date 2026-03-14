<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseType extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_types';
    
    public $timestamps = true;

    // We keep the name 'exchange_product_id' even though it now holds an array/JSON
    protected $fillable = ['business_id', 'name', 'description', 'exchange_product_id'];

    protected $dates = ['deleted_at'];

    // [NEW] Automatically convert the JSON column to a PHP Array
    protected $casts = [
        'exchange_product_id' => 'array',
    ];

    /**
     * Get purchase lines associated with this purchase type.
     * (KEEP THIS)
     */
    public function purchase_lines()
    {
        return $this->hasMany(\App\PurchaseLine::class, 'purchase_type_id');
    }

    /**
     * Get business associated with this purchase type.
     * (KEEP THIS)
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }
}