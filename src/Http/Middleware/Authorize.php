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
        if (Gate::has('viewTracker')) {
            return Gate::allows('viewTracker', $request->user());
        }

        return in_array(app()->environment(), ['local', 'testing'], true);
    }
}
