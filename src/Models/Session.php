<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $visitor_uuid
 * @property string|null $user_id
 * @property string $client_ip
 * @property string $user_agent
 * @property string|null $device_kind
 * @property string|null $device_platform
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $language
 * @property string|null $language_range
 * @property bool $is_robot
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $page_views_count
 * @property int $events_count
 * @property Carbon $started_at
 * @property Carbon $last_activity_at
 * @property Carbon|null $ended_at
 */
class Session extends BaseModel
{
    protected $table = 'tracker_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'is_robot' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'page_views_count' => 'integer',
        'events_count' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<User> $model */
        $model = config('auth.providers.users.model') ?? User::class;

        return $this->belongsTo($model, 'user_id');
    }

    /**
     * @return HasMany<PageView, $this>
     */
    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'session_id');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'session_id');
    }
}
