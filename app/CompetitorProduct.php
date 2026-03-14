<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompetitorProduct extends Model
{
    protected $table = 'competitor_product';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'business_id', 'own_product_sku', 'competitor_product1_sku', 'competitor_product2_sku'
    ];
}