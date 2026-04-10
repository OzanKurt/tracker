<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    protected $table = 'tracker_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'is_robot'         => 'boolean',
        'latitude'         => 'float',
        'longitude'        => 'float',
        'started_at'       => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at'         => 'datetime',
        'page_views_count' => 'integer',
        'events_count'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'session_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'session_id');
    }
}
