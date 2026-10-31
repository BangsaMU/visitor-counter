<?php

namespace Bangsamu\VisitorCounter;

use Bangsamu\VisitorCounter\Console\SyncSchemaCommand;
use Bangsamu\VisitorCounter\Http\Middleware\RecordVisit;
use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\ClientIpResolver;
use Bangsamu\VisitorCounter\Support\ReportedClientIp;
use Bangsamu\VisitorCounter\Support\UserDirectory;
use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Cukup `composer require`: middleware masuk grup `web`, tabel dibuat/dilengkapi otomatis, route dashboard dan
 * pelapor IP terdaftar, prune terjadwal. Semua bisa diatur lewat config `visitor-counter` (publish bila perlu).
 */
class VisitorCounterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visitor-counter.php', 'visitor-counter');

        $this->app->singleton(VisitorSchema::class);
        $this->app->singleton(ReportedClientIp::class);
        $this->app->singleton(ClientIpResolver::class);
        $this->app->singleton(UserDirectory::class);
        $this->app->singleton(VisitRecorder::class);
        $this->app->singleton(VisitorStats::class);
        $this->app->alias(VisitorStats::class, 'visitor-counter');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visitor-counter');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->registerGate();
        $this->registerMiddleware();
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncSchemaCommand::class]);

            $this->publishes([
                __DIR__.'/../config/visitor-counter.php' => config_path('visitor-counter.php'),
            ], 'visitor-counter-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/visitor-counter'),
            ], 'visitor-counter-views');
        }
    }

    /**
     * Gate dashboard bawaan: user dengan salah satu ability `dashboard.abilities`. App boleh mendefinisikan ulang.
     */
    private function registerGate(): void
    {
        if (Gate::has('viewVisitorCounter')) {
            return;
        }

        Gate::define('viewVisitorCounter', function (Authenticatable $user): bool {
            $abilities = array_values(array_filter((array) config('visitor-counter.dashboard.abilities', []), 'is_string'));

            return $abilities !== [] && Gate::forUser($user)->any($abilities);
        });
    }

    /**
     * Tambahkan RecordVisit ke grup middleware lewat HTTP kernel (bukan router saja), supaya tidak tertimpa saat kernel
     * menyinkronkan grupnya ke router.
     */
    private function registerMiddleware(): void
    {
        $groups = array_values(array_filter((array) config('visitor-counter.middleware_groups', []), 'is_string'));
        if ($groups === []) {
            return;
        }

        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel) use ($groups): void {
            if (! $kernel instanceof HttpKernel) {
                return;
            }

            foreach ($groups as $group) {
                if (array_key_exists($group, $kernel->getMiddlewareGroups())) {
                    $kernel->appendMiddlewareToGroup($group, RecordVisit::class);
                }
            }
        });
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ((int) config('visitor-counter.retention_days', 0) <= 0 || ! config('visitor-counter.prune.schedule', true)) {
                return;
            }

            $schedule->command('model:prune', ['--model' => [Visitor::class]])
                ->dailyAt((string) config('visitor-counter.prune.at', '02:30'))
                ->withoutOverlapping();
        });
    }
}
