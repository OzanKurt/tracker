<?php

namespace Kurt\Tracker\Models;

class Cookie extends Base
{
    protected $table = 'tracker_cookies';

    protected $fillable = ['uuid'];
}
