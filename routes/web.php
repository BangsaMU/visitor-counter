<?php

use Bangsamu\VisitorCounter\Http\Controllers\ClientIpController;
use Bangsamu\VisitorCounter\Http\Controllers\DashboardController;
use Bangsamu\VisitorCounter\Support\ClientIpResolver;
use Illuminate\Support\Facades\Route;

if (config('visitor-counter.dashboard.enabled', true)) {
    Route::middleware((array) config('visitor-counter.dashboard.middleware', ['web', 'auth']))
        ->prefix((string) config('visitor-counter.dashboard.path', 'visitor-counter'))
        ->name('visitor-counter.')
        ->group(function (): void {
            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::get('stats', [DashboardController::class, 'stats'])->name('stats');
            Route::get('today', [DashboardController::class, 'today'])->name('today');
            Route::post('toggle', [DashboardController::class, 'toggle'])->name('toggle');
            Route::post('settings', [DashboardController::class, 'saveSettings'])->name('settings');
        });
}

if (config('visitor-counter.client_ip.report_route.enabled', true)) {
    Route::middleware((array) config('visitor-counter.client_ip.report_route.middleware', ['web', 'throttle:10,1']))
        ->post((string) config('visitor-counter.client_ip.report_route.path', 'visitor-counter/client-ip'), [ClientIpController::class, 'store'])
        ->name(ClientIpResolver::REPORT_ROUTE);
}
