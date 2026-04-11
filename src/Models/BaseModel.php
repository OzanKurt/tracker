<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    public function getConnectionName(): ?string
    {
        $configured = config('tracker.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return parent::getConnectionName();
    }
}
