<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int                 $id
 * @property string              $ip_hash
 * @property string|null         $country_code
 * @property string|null         $country_name
 * @property string|null         $city
 * @property float|null          $latitude
 * @property float|null          $longitude
 * @property string              $provider
 * @property Carbon              $cached_until
 * @property Carbon              $created_at
 */
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
