<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_page_views';

    protected $guarded = ['id'];

    protected $casts = [
        'route_params' => 'array',
        'query_params' => 'array',
        'status_code'  => 'integer',
        'duration_ms'  => 'integer',
        'created_at'   => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
