<?php

namespace Kurt\Tracker\Models;

class Error extends Base
{
    protected $table = 'tracker_errors';

    protected $fillable = [
        'message',
        'code',
    ];
}
