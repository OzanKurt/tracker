<?php

namespace OzanKurt\Tracker\Models;

class Path extends Base
{
    protected $table = 'tracker_paths';

    protected $fillable = [
        'path',
    ];
}
