<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class VisitorCookie
{
    private ?Cookie $issuedCookie = null;

    public function readOrIssue(Request $request): string
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor');
        $existing = $request->cookies->get($name);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $uuid = (string) Str::uuid();

        $this->issuedCookie = Cookie::create(
            name: $name,
            value: $uuid,
            expire: time() + ((int) config('tracker.cookie.lifetime_days', 365) * 86400),
            path: '/',
            domain: null,
            secure: (bool) config('tracker.cookie.secure', true),
            httpOnly: (bool) config('tracker.cookie.http_only', true),
            raw: false,
            sameSite: $this->resolveSameSite((string) config('tracker.cookie.same_site', 'lax')),
        );

        return $uuid;
    }

    public function issuedCookie(): ?Cookie
    {
        return $this->issuedCookie;
    }

    /** @return Cookie::SAMESITE_*|''|null */
    private function resolveSameSite(string $value): ?string
    {
        return match (strtolower($value)) {
            Cookie::SAMESITE_LAX => Cookie::SAMESITE_LAX,
            Cookie::SAMESITE_STRICT => Cookie::SAMESITE_STRICT,
            Cookie::SAMESITE_NONE => Cookie::SAMESITE_NONE,
            '' => '',
            default => null,
        };
    }
}
