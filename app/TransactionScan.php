<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionScan extends Model
{
   // Specifying the table name is optional if it follows Laravel's naming convention
   protected $table = 'warehouse_scan';  // Only necessary if the table name cannot be guessed correctly from the model name

   // If you don't want Laravel to handle timestamps, set this to false
   public $timestamps = false;

   // Define guarded attributes that are not mass assignable
   protected $guarded = ['id'];

   public function sales_person()
   {
       return $this->belongsTo(\App\User::class, 'created_by');
   }
   public function TransactionSellLineScan()
   {
       return $this->hasMany(TransactionSellLineScan::class, 'transactions_scan_id', 'id');
   }

   public function createdByUser()
   {
       return $this->belongsTo(\App\User::class, 'created_by');
   }

   public function updatedByUser()
   {
       return $this->belongsTo(\App\User::class, 'updated_by');
   }
}
