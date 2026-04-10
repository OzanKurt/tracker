<?php

declare(strict_types=1);

use OzanKurt\Tracker\Tracker;

it('binds the Tracker service as a singleton', function () {
    $first = app(Tracker::class);
    $second = app(Tracker::class);

    expect($first)->toBeInstanceOf(Tracker::class)
        ->and($first)->toBe($second);
});

it('loads the tracker config', function () {
    expect(config('tracker.enabled'))->toBeTrue()
        ->and(config('tracker.dispatcher'))->toBe('queue')
        ->and(config('tracker.privacy.anonymize_ip'))->toBeTrue();
});
