<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\Support\PrivacyFilter;
use OzanKurt\Tracker\Support\VisitorCookie;
use Symfony\Component\HttpFoundation\Response;

class TrackRequests
{
    public function __construct(
        private readonly PrivacyFilter $privacy,
        private readonly VisitorCookie $visitor,
        private readonly DispatcherManager $dispatchers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->privacy->shouldTrack($request)) {
            return $next($request);
        }

        $visitorUuid = $this->visitor->readOrIssue($request);
        $sessionId = $this->resolveSessionId($request, $visitorUuid);

        $route = $request->route();

        $payload = Payload::fromArray([
            'ip' => (string) ($request->ip() ?? ''),
            'user_agent' => (string) $request->userAgent(),
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $route !== null ? $route->getName() : null,
            'route_action' => $route !== null ? $route->getActionName() : null,
            'route_params' => $route !== null ? (array) $route->parameters() : [],
            'query_params' => $request->query(),
            'visitor_uuid' => $visitorUuid,
            'session_id' => $sessionId,
            'user_id' => $request->user() !== null ? $request->user()->getAuthIdentifier() : null,
            'referer' => $request->headers->get('referer'),
            'language_range' => (string) $request->headers->get('accept-language', ''),
            'captured_at' => Carbon::now()->toIso8601String(),
        ]);

        $this->dispatchers->driver()->dispatchPageView($payload);

        /** @var Response $response */
        $response = $next($request);

        $issued = $this->visitor->issuedCookie();
        if ($issued !== null) {
            $response->headers->setCookie($issued);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ((string) config('tracker.dispatcher', 'queue') === 'defer') {
            $this->dispatchers->driver()->flush();
        }
    }

    private function resolveSessionId(Request $request, string $visitorUuid): string
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $key = 'tracker.session_uuid';
            $existing = $session->get($key);
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
            $generated = (string) Str::uuid();
            $session->put($key, $generated);

            return $generated;
        }

        // Fall back to visitor-scoped session id when no Laravel session.
        return $visitorUuid;
    }
}
