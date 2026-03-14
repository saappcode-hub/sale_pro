<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrentStockBackup extends Model
{
    use HasFactory;

    // Specifying the table name is optional if it follows Laravel's naming convention
    protected $table = 'stock_movements';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];
}
