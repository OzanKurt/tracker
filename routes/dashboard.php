<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\EventsController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\PageViewsController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\SessionsController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\UsersController;

Route::get('/', OverviewController::class)->name('overview');
Route::get('/sessions', [SessionsController::class, 'index'])->name('sessions.index');
Route::get('/sessions/{uuid}', [SessionsController::class, 'show'])->name('sessions.show');
Route::get('/page-views', PageViewsController::class)->name('page-views');
Route::get('/events', EventsController::class)->name('events');
Route::get('/users/{id}', [UsersController::class, 'show'])->name('users.show');
