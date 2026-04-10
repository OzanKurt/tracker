<?php

declare(strict_types=1);

use OzanKurt\Tracker\Support\RefererParser;

it('parses a google search referer', function () {
    $parser = new RefererParser;
    $result = $parser->parse('https://www.google.com/search?q=ozankurt+tracker', 'example.com');

    expect($result->medium)->toBe('search')
        ->and($result->source)->toBe('google')
        ->and($result->searchTerm)->toBe('ozankurt tracker')
        ->and($result->url)->toBe('https://www.google.com/search?q=ozankurt+tracker')
        ->and($result->domain)->toBe('www.google.com');
});

it('parses a twitter social referer', function () {
    $parser = new RefererParser;
    $result = $parser->parse('https://t.co/abc123', 'example.com');

    expect($result->medium)->toBe('social')
        ->and($result->source)->toBe('twitter')
        ->and($result->searchTerm)->toBeNull();
});

it('returns null metadata for an internal referer (same host)', function () {
    $parser = new RefererParser;
    $result = $parser->parse('https://example.com/about', 'example.com');

    expect($result->medium)->toBe('internal')
        ->and($result->source)->toBeNull();
});

it('returns direct when referer is null or empty', function () {
    $parser = new RefererParser;
    $result = $parser->parse(null, 'example.com');

    expect($result->medium)->toBe('direct')
        ->and($result->url)->toBeNull();
});
