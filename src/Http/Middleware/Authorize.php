<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAuthorized($request)) {
            return $next($request);
        }

        abort(403, 'Unauthorized to access tracker dashboard.');
    }

    private function isAuthorized(Request $request): bool
    {
        $gate = (string) config('tracker.dashboard.gate', 'viewTracker');

        if ($gate !== '' && Gate::has($gate)) {
            return Gate::allows($gate, $request->user());
        }

        // No gate registered. Allow only in environments the app has opted
        // into via `dashboard.allow_without_gate_envs` (defaults to local +
        // testing). Setting the list to [] forces every environment to
        // register the gate, including local — production never falls open.
        /** @var array<int, string> $allowedEnvs */
        $allowedEnvs = (array) config('tracker.dashboard.allow_without_gate_envs', ['local', 'testing']);

        return in_array(app()->environment(), $allowedEnvs, true);
    }
}
