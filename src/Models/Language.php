<?php

namespace OzanKurt\Tracker\Models;

class Language extends Base
{
    protected $table = 'tracker_languages';

    protected $fillable = ['preference', 'language-range'];
}
