<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;

class GeoIpCache extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_geoip_cache';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'cached_until' => 'datetime',
        'created_at' => 'datetime',
    ];
}
