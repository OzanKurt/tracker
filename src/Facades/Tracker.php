<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

/**
 * @method static bool isEnabled()
 * @method static void enable()
 * @method static void disable()
 * @method static ?Session currentSession()
 * @method static ?string sessionId()
 * @method static ?string visitorId()
 * @method static Collection<int, Session> sessions(int $minutes = 1440)
 * @method static Collection<int, Session> onlineUsers(int $minutes = 3)
 * @method static Collection<int, Session> users(int $minutes = 1440)
 * @method static Collection<int, PageView> pageViews(int $minutes = 1440)
 * @method static Collection<int, Event> events(int $minutes = 1440, ?string $name = null)
 * @method static void logEvent(string $name, array<string, mixed> $payload = [])
 * @method static void optOut()
 * @method static void optIn()
 * @method static bool hasOptedOut()
 *
 * @see \OzanKurt\Tracker\Tracker
 */
class Tracker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OzanKurt\Tracker\Tracker::class;
    }
}
