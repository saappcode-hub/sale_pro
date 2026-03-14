<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelShipping extends Model
{
    use HasFactory;

   // Specifying the table name is optional if it follows Laravel's naming convention
   protected $table = 'label_shipping';  // Only necessary if the table name cannot be guessed correctly from the model name

   // If you don't want Laravel to handle timestamps, set this to false
   public $timestamps = false;

   // Define guarded attributes that are not mass assignable
   protected $guarded = ['id'];

    public function labelShipping()
    {
        return $this->belongsTo(LabelShipping::class, 'label_shipping_id');
    }
}
