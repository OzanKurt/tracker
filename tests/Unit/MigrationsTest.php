<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../database/migrations'));

it('creates tracker_sessions with expected columns', function () {
    expect(Schema::hasTable('tracker_sessions'))->toBeTrue();

    foreach ([
        'id', 'uuid', 'visitor_uuid', 'user_id', 'client_ip', 'user_agent',
        'device_kind', 'device_platform', 'browser', 'browser_version',
        'language', 'is_robot',
        'country_code', 'country_name', 'city', 'latitude', 'longitude',
        'referer_url', 'referer_domain', 'referer_medium',
        'started_at', 'last_activity_at', 'ended_at',
        'page_views_count', 'events_count',
        'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('tracker_sessions', $column))
            ->toBeTrue("Column {$column} missing from tracker_sessions");
    }
});

it('creates tracker_page_views with expected columns', function () {
    expect(Schema::hasTable('tracker_page_views'))->toBeTrue();
    foreach (['id', 'session_id', 'method', 'path', 'route_name',
        'route_params', 'query_params', 'status_code', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_page_views', $c))->toBeTrue();
    }
});

it('creates tracker_events with expected columns', function () {
    expect(Schema::hasTable('tracker_events'))->toBeTrue();
    foreach (['id', 'session_id', 'name', 'payload', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_events', $c))->toBeTrue();
    }
});

it('creates tracker_geoip_cache with expected columns', function () {
    expect(Schema::hasTable('tracker_geoip_cache'))->toBeTrue();
    foreach (['id', 'ip_hash', 'country_code', 'country_name',
        'city', 'latitude', 'longitude', 'provider',
        'cached_until', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_geoip_cache', $c))->toBeTrue();
    }
});
