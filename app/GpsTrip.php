<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GpsTrip extends Model
{
    protected $table = 'gps_trips';
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'business_id',
        'trip_date',
        'clock_in_time',
        'clock_out_time',
        'start_location',
        'end_location'
    ];

    // One trip has many GPS points
    public function gpsPoints()
    {
        return $this->hasMany(GpsPoint::class, 'trip_id', 'id');
    }

    // Trip belongs to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Trip belongs to Business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }
}