<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Support\VisitorCookie;

class Tracker
{
    private bool $enabled = true;

    public function __construct(
        private readonly DispatcherManager $dispatchers,
        private readonly VisitorCookie $visitor,
    ) {}

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && (bool) config('tracker.enabled', true);
    }

    public function currentSession(): ?Session
    {
        $uuid = $this->sessionId();
        if ($uuid === null) {
            return null;
        }

        return Session::where('uuid', $uuid)->first();
    }

    public function sessionId(): ?string
    {
        $request = request();
        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get('tracker.session_uuid');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function visitorId(): ?string
    {
        $request = request();
        if (! $request instanceof Request) {
            return null;
        }

        $name = (string) config('tracker.cookie.name', 'tracker_visitor');
        $value = $request->cookies->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return Collection<int, Session>
     */
    public function sessions(int $minutes = 1440): Collection
    {
        return Session::where('last_activity_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @return Collection<int, Session>
     */
    public function onlineUsers(int $minutes = 3): Collection
    {
        return $this->sessions($minutes);
    }

    /**
     * @return Collection<int, Session>
     */
    public function users(int $minutes = 1440): Collection
    {
        return Session::whereNotNull('user_id')
            ->where('last_activity_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @return Collection<int, PageView>
     */
    public function pageViews(int $minutes = 1440): Collection
    {
        return PageView::where('created_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    public function events(int $minutes = 1440, ?string $name = null): Collection
    {
        $query = Event::where('created_at', '>=', Carbon::now()->subMinutes($minutes));
        if ($name !== null) {
            $query->where('name', $name);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logEvent(string $name, array $payload = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->dispatchers->driver()->dispatchEvent(
            $this->payloadFromContext(),
            $name,
            $payload,
        );
    }

    public function optOut(): void
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor').'_optout';
        Cookie::queue(Cookie::make(
            name: $name,
            value: '1',
            minutes: (int) config('tracker.cookie.lifetime_days', 365) * 24 * 60,
        ));
    }

    public function optIn(): void
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor').'_optout';
        Cookie::queue(Cookie::forget($name));
    }

    public function hasOptedOut(): bool
    {
        $request = request();
        if (! $request instanceof Request) {
            return false;
        }
        $name = (string) config('tracker.cookie.name', 'tracker_visitor').'_optout';

        return $request->cookies->has($name);
    }

    private function payloadFromContext(): Payload
    {
        $request = request() instanceof Request ? request() : null;
        $now = Carbon::now()->toIso8601String();

        if ($request === null) {
            return Payload::fromArray([
                'ip' => '0.0.0.0', 'user_agent' => 'cli',
                'method' => 'CLI', 'url' => 'cli://tracker', 'path' => '/',
                'route_name' => null, 'route_action' => null,
                'route_params' => [], 'query_params' => [],
                'visitor_uuid' => (string) Str::uuid(),
                'session_id' => (string) Str::uuid(),
                'user_id' => null, 'referer' => null,
                'language_range' => '', 'captured_at' => $now,
            ]);
        }

        $visitorUuid = $this->visitor->readOrIssue($request);
        $sessionId = $this->sessionId() ?? $visitorUuid;

        $route = $request->route();

        return Payload::fromArray([
            'ip'             => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent'     => (string) $request->userAgent(),
            'method'         => $request->getMethod(),
            'url'            => $request->fullUrl(),
            'path'           => '/'.ltrim($request->path(), '/'),
            'route_name'     => $route !== null ? $route->getName() : null,
            'route_action'   => $route !== null ? $route->getActionName() : null,
            'route_params'   => $route !== null ? $route->parameters() : [],
            'query_params'   => $request->query(),
            'visitor_uuid'   => $visitorUuid,
            'session_id'     => $sessionId,
            'user_id'        => $request->user() !== null ? $request->user()->getAuthIdentifier() : null,
            'referer'        => $request->headers->get('referer'),
            'language_range' => (string) $request->headers->get('accept-language', ''),
            'captured_at'    => $now,
        ]);
    }
}
