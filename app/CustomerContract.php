<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerContract extends Model
{
    protected $table = 'customer_contracts';
    
    protected $fillable = [
        'business_id',
        'contact_id',
        'contract_name',
        'reference_no',
        'start_date',
        'end_date',
        'total_target_units',
        'total_contract_value',
        'created_by',
    ];  

    protected $dates = ['start_date', 'end_date', 'created_at', 'updated_at'];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products()
    {
        return $this->hasMany(ContractProduct::class, 'contract_id');
    }

    /**
     * Get all of the contract's media (documents).
     */
    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }
}