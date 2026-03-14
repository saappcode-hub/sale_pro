<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContactMap extends Model
{
    protected $table = 'contacts_map';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [   
            'contact_id',
            'points',
            'address',
            'address_note',
            'province_id',
            'district_id',
            'commune_id',
            'created_at',
            'updated_at'];
    
    public function CambodiaProvince()
    {
        return $this->hasOne(CambodiaProvince::class, 'id', 'province_id');
    }
    public function CambodiaDistrict()
    {
        return $this->hasOne(CambodiaDistrict::class, 'id', 'district_id');
    }
    public function CambodiaCommune()
    {
        return $this->hasOne(CambodiaCommune::class, 'id', 'commune_id');
    }
}
