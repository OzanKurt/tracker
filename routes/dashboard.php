<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;

Route::get('/', OverviewController::class)->name('overview');
