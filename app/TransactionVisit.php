<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionVisit extends Model
{
    // Specifying the table name is optional if it follows Laravel's naming convention
    protected $table = 'transactions_visit';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];

    // Inside Currency model
    public function TransactionSellLineVisit()
    {
        return $this->hasMany(TransactionSellLineVisit::class, 'transaction_id', 'id');
    }

    public function TransactionSellLineVisitImage()
    {
        return $this->hasMany(TransactionSellLineVisitImage::class, 'transaction_id', 'id');
    }

    public function business()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }

    public function location()
    {
        return $this->hasOne(BusinessLocation::class, 'id', 'location_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function sales_person()
    {
        return $this->hasOne(User::class, 'id', 'create_by');
    }

}
