<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSupplierCashRing extends Model
{
    protected $table = 'transactions_supplier_cash_ring';  // Only necessary if the table name cannot be guessed correctly from the model name

    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;

    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];
}
