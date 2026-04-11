<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property string $name
 * @property array<string,mixed>|null $payload
 * @property Carbon $created_at
 */
class Event extends BaseModel
{
    public $timestamps = false;

    protected $table = 'tracker_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
