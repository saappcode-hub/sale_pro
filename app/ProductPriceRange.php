<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductPriceRange extends Model
{
    protected $table = 'product_price_ranges';
    public $timestamps = false;
    protected $guarded = ['id'];
}
