<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserLocationZone extends Model
{
    protected $table = 'user_location_zones';

    protected $fillable = [
        'user_id',
        'province_id',
        'district_id',
        'commune_id',
    ];

    public $timestamps = true;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function province()
    {
        return $this->belongsTo(CambodiaProvince::class, 'province_id');
    }

    public function district()
    {
        return $this->belongsTo(CambodiaDistrict::class, 'district_id');
    }

    public function commune()
    {
        return $this->belongsTo(CambodiaCommune::class, 'commune_id');
    }
}