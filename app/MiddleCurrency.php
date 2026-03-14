<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MiddleCurrency extends Model
{
    // Specifying the table name is optional if it follows Laravel's naming convention
    protected $table = 'middle_currencies';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];

    protected $fillable = [
        'store_id',
        'currency_id',
        'exchange_rate',
        'created_at',
        'updated_at',
    ];
}
