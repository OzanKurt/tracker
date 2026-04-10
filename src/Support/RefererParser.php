<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

final class RefererParser
{
    /** @var array<string, array{medium: string, source: string, search_param?: string}> */
    private const KNOWN_HOSTS = [
        'google.com' => ['medium' => 'search', 'source' => 'google',     'search_param' => 'q'],
        'www.google.com' => ['medium' => 'search', 'source' => 'google',     'search_param' => 'q'],
        'bing.com' => ['medium' => 'search', 'source' => 'bing',       'search_param' => 'q'],
        'www.bing.com' => ['medium' => 'search', 'source' => 'bing',       'search_param' => 'q'],
        'duckduckgo.com' => ['medium' => 'search', 'source' => 'duckduckgo', 'search_param' => 'q'],
        'yandex.com' => ['medium' => 'search', 'source' => 'yandex',     'search_param' => 'text'],
        'twitter.com' => ['medium' => 'social', 'source' => 'twitter'],
        'x.com' => ['medium' => 'social', 'source' => 'twitter'],
        't.co' => ['medium' => 'social', 'source' => 'twitter'],
        'facebook.com' => ['medium' => 'social', 'source' => 'facebook'],
        'www.facebook.com' => ['medium' => 'social', 'source' => 'facebook'],
        'm.facebook.com' => ['medium' => 'social', 'source' => 'facebook'],
        'linkedin.com' => ['medium' => 'social', 'source' => 'linkedin'],
        'www.linkedin.com' => ['medium' => 'social', 'source' => 'linkedin'],
        'reddit.com' => ['medium' => 'social', 'source' => 'reddit'],
        'www.reddit.com' => ['medium' => 'social', 'source' => 'reddit'],
        'news.ycombinator.com' => ['medium' => 'social', 'source' => 'hackernews'],
    ];

    public function parse(?string $refererUrl, string $currentHost): RefererResult
    {
        if ($refererUrl === null || $refererUrl === '') {
            return new RefererResult(
                url: null, domain: null, medium: 'direct', source: null, searchTerm: null,
            );
        }

        $host = parse_url($refererUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return new RefererResult(
                url: $refererUrl, domain: null, medium: 'direct', source: null, searchTerm: null,
            );
        }

        if (strcasecmp($host, $currentHost) === 0) {
            return new RefererResult(
                url: $refererUrl, domain: $host, medium: 'internal', source: null, searchTerm: null,
            );
        }

        $lookup = self::KNOWN_HOSTS[strtolower($host)] ?? null;

        if ($lookup === null) {
            return new RefererResult(
                url: $refererUrl, domain: $host, medium: 'referral', source: $host, searchTerm: null,
            );
        }

        $searchTerm = null;
        if (isset($lookup['search_param'])) {
            $query = parse_url($refererUrl, PHP_URL_QUERY);
            if (is_string($query)) {
                parse_str($query, $parsed);
                $raw = $parsed[$lookup['search_param']] ?? null;
                if (is_string($raw) && $raw !== '') {
                    $searchTerm = $raw;
                }
            }
        }

        return new RefererResult(
            url: $refererUrl,
            domain: $host,
            medium: $lookup['medium'],
            source: $lookup['source'],
            searchTerm: $searchTerm,
        );
    }
}

final class RefererResult
{
    public function __construct(
        public readonly ?string $url,
        public readonly ?string $domain,
        public readonly string $medium,
        public readonly ?string $source,
        public readonly ?string $searchTerm,
    ) {}
}
