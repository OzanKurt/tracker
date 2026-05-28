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

    /**
     * Mask the host portion of an IP when `tracker.privacy.anonymize_ip` is on.
     * Off by default — flip the env to opt in. IPv4 keeps the /24 (zeros the
     * last octet); IPv6 keeps the /48 (zeros the last 80 bits).
     */
    public function anonymize(string $ip): string
    {
        if ($ip === '') {
            return $ip;
        }

        if (! (bool) config('tracker.privacy.anonymize_ip', false)) {
            return $ip;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }

        if (strlen($packed) === 4) {
            $masked = substr($packed, 0, 3)."\0";
        } else {
            $masked = substr($packed, 0, 6).str_repeat("\0", 10);
        }

        $result = @inet_ntop($masked);

        return $result === false ? $ip : $result;
    }

    /**
     * Replace values whose keys match any glob in `tracker.privacy.scrub_param_keys`.
     * Returns the array unchanged when no patterns are configured.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function scrub(array $params): array
    {
        /** @var array<int, string> $patterns */
        $patterns = (array) config('tracker.privacy.scrub_param_keys', []);
        if ($patterns === [] || $params === []) {
            return $params;
        }

        foreach ($params as $key => $value) {
            $stringKey = (string) $key;
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $stringKey)) {
                    $params[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $params;
    }

    private function isIgnoredRoute(Request $request): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = (array) config('tracker.routes.ignore', []);

        if ((bool) config('tracker.dashboard.enabled', true)) {
            $dashboardPath = trim((string) config('tracker.dashboard.path', 'tracker'), '/');
            if ($dashboardPath !== '') {
                $patterns[] = $dashboardPath;
                $patterns[] = $dashboardPath.'/*';
            }
        }

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
