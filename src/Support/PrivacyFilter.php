<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrivacyFilter
{
    public function shouldTrack(Request $request): bool
    {
        if (! (bool) config('tracker.enabled', true)) {
            return false;
        }

        if ($this->isIgnoredRoute($request)) {
            return false;
        }

        if ($this->isDntRequest($request)) {
            return false;
        }

        if ($this->hasOptedOut($request)) {
            return false;
        }

        return true;
    }

    public function hasOptedOut(Request $request): bool
    {
        $cookieName = (string) config('tracker.cookie.name', 'tracker_visitor').'_optout';

        return $request->cookies->has($cookieName);
    }

    private function isIgnoredRoute(Request $request): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = (array) config('tracker.routes.ignore', []);
        $path = ltrim($request->path(), '/');

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function isDntRequest(Request $request): bool
    {
        if (! (bool) config('tracker.privacy.respect_dnt', false)) {
            return false;
        }

        return $request->headers->get('DNT') === '1';
    }
}
