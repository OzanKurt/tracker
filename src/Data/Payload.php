<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Data;

final class Payload
{
    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $queryParams
     */
    public function __construct(
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly string $method,
        public readonly string $url,
        public readonly string $path,
        public readonly ?string $routeName,
        public readonly ?string $routeAction,
        public readonly array $routeParams,
        public readonly array $queryParams,
        public readonly string $visitorUuid,
        public readonly string $sessionId,
        public readonly int|string|null $userId,
        public readonly ?string $referer,
        public readonly string $languageRange,
        public readonly string $capturedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ip:            (string) $data['ip'],
            userAgent:     (string) $data['user_agent'],
            method:        (string) $data['method'],
            url:           (string) $data['url'],
            path:          (string) $data['path'],
            routeName:     $data['route_name'] ?? null,
            routeAction:   $data['route_action'] ?? null,
            routeParams:   (array) ($data['route_params'] ?? []),
            queryParams:   (array) ($data['query_params'] ?? []),
            visitorUuid:   (string) $data['visitor_uuid'],
            sessionId:     (string) $data['session_id'],
            userId:        $data['user_id'] ?? null,
            referer:       $data['referer'] ?? null,
            languageRange: (string) ($data['language_range'] ?? ''),
            capturedAt:    (string) $data['captured_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip'             => $this->ip,
            'user_agent'     => $this->userAgent,
            'method'         => $this->method,
            'url'            => $this->url,
            'path'           => $this->path,
            'route_name'     => $this->routeName,
            'route_action'   => $this->routeAction,
            'route_params'   => $this->routeParams,
            'query_params'   => $this->queryParams,
            'visitor_uuid'   => $this->visitorUuid,
            'session_id'     => $this->sessionId,
            'user_id'        => $this->userId,
            'referer'        => $this->referer,
            'language_range' => $this->languageRange,
            'captured_at'    => $this->capturedAt,
        ];
    }
}
