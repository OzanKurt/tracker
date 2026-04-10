<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\SessionsController;

Route::get('/', OverviewController::class)->name('overview');
Route::get('/sessions', [SessionsController::class, 'index'])->name('sessions.index');
