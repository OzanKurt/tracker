<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use Illuminate\Contracts\Foundation\Application;
use OzanKurt\Tracker\Support\Pipeline;

class DispatcherManager
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function driver(): DispatcherInterface
    {
        return match ((string) config('tracker.dispatcher', 'queue')) {
            'sync'  => new SyncDispatcher($this->app->make(Pipeline::class)),
            'defer' => $this->app->make(DeferredDispatcher::class),
            default => new QueueDispatcher(),
        };
    }
}
