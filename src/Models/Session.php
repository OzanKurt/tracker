<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;

class Session extends Model
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
