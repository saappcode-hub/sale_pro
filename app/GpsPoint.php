<?php

namespace App;

use App\User;
use Illuminate\Database\Eloquent\Model;

class GpsPoint extends Model
{
    protected $table = 'gps_points';
    public $timestamps = true;

    protected $fillable = [
        'trip_id',
        'user_id',
        'location',
        'gps_time'
    ];

    // Relationship: Point belongs to Trip
    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    // Relationship: Point belongs to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Helper: Parse location string to lat/long
    public function getCoordinates()
    {
        if (!$this->location) return null;
        $coords = explode(',', $this->location);
        return [
            'latitude' => (float) trim($coords[0] ?? null),
            'longitude' => (float) trim($coords[1] ?? null)
        ];
    }
}
