<?php

declare(strict_types=1);

use OzanKurt\Tracker\Support\BotFilter;

it('returns true for a known crawler user agent', function () {
    $filter = new BotFilter;
    expect($filter->isBot('Googlebot/2.1 (+http://www.google.com/bot.html)'))->toBeTrue();
});

it('returns false for a typical browser user agent', function () {
    $filter = new BotFilter;
    expect($filter->isBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/120.0.0.0 Safari/537.36'))->toBeFalse();
});
