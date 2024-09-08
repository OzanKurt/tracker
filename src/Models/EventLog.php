<?php

namespace OzanKurt\Tracker\Models;

class EventLog extends Base
{
    protected $table = 'tracker_events_log';

    protected $fillable = [
        'event_id',
        'class_id',
        'log_id',
    ];
}
