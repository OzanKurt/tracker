<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \OzanKurt\Tracker\Tracker
 */
class Tracker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OzanKurt\Tracker\Tracker::class;
    }
}
