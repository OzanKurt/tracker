<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property string $method
 * @property string $path
 * @property string|null $route_name
 * @property string|null $route_action
 * @property array<string,mixed> $route_params
 * @property array<string,mixed> $query_params
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property Carbon $created_at
 */
class PageView extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_page_views';

    protected $guarded = ['id'];

    protected $casts = [
        'route_params' => 'array',
        'query_params' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
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
