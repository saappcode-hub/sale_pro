<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RingUnit extends Model
{
    protected $table = 'ring_unit';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'business_id', 
        'product_id', 
        'value', 
        'created_by', 
        'created_at', 
        'updated_at'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
