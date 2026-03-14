<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $table = 'shipping_address';

    // Enable timestamps since the table has created_at and updated_at columns
    public $timestamps = true;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];

    // Define fillable fields
    protected $fillable = [
        'business_id',
        'contact_id',
        'label_shipping_id',
        'mobile',
        'address',
        'map',
        'latlong',
        'is_default',
        'created_by',
        'created_at',
        'updated_at'
    ];

    // Relationship with Business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    // Relationship with LabelShipping
    public function labelShipping()
    {
        return $this->belongsTo(LabelShipping::class, 'label_shipping_id');
    }
}