<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use OzanKurt\Agent\Agent;

class BotFilter
{
    public function isBot(string $userAgent): bool
    {
        return (new Agent())->isRobot($userAgent);
    }
}
