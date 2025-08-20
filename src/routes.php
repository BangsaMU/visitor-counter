<?php

use Illuminate\Support\Facades\Route;
use Bangsamu\VisitorCounter\Http\Controllers\VisitorApiController;

Route::middleware(['web','auth'])->prefix('visitor-counter')->group(function () {
    Route::get('dashboard', [VisitorApiController::class, 'index']);
    Route::get('stats', [VisitorApiController::class, 'stats']);
    Route::get('today', [VisitorApiController::class, 'today']);
});
