<?php

namespace Bangsamu\VisitorCounter;

use Illuminate\Support\ServiceProvider;
use Bangsamu\VisitorCounter\Middleware\CountVisitor;
use Bangsamu\VisitorCounter\Models\Visitor;
use Illuminate\Contracts\Http\Kernel;

class VisitorCounterServiceProvider extends ServiceProvider
{
    /**
     * The prefix to use for register/load the package resources.
     *
     * @var string
     */
    protected $pkgPrefix = '';

    public function register()
    {
        $this->app->singleton('visitor-counter', function () {
            return new class {
                public function total()
                {
                    return Visitor::count();
                }

                public function today()
                {
                    return Visitor::where('visit_date', now()->toDateString())->count();
                }

                public function monthly()
                {
                    return Visitor::whereMonth('visit_date', now()->month)->count();
                }

                public function unique()
                {
                    return Visitor::distinct('ip')->count('ip');
                }
            };
        });
    }

    // public function boot()
    // {
    //     $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

    //     // Tambahkan middleware global jika mau
    //     $this->app['router']->pushMiddlewareToGroup('web', CountVisitor::class);
    // }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadConfig();
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visitor-counter');


        // Middleware global
        // $this->app['router']->pushMiddlewareToGroup('web', Middleware\CountVisitor::class);
        // $this->app['router']->pushMiddlewareToGroup('web', CountVisitor::class);
        // $this->app['router']->pushMiddlewareToGroup('web', Middleware\RecordVisitor::class);

                // Daftarkan middleware global
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(CountVisitor::class);

    }


    /**
     * Load the package config.
     *
     * @return void
     */
    private function loadConfig()
    {
        $configPath = $this->packagePath('resources/config/' . ucfirst($this->pkgPrefix) . 'visitor-counter' . '.php');
        $this->mergeConfigFrom($configPath, ucfirst($this->pkgPrefix . 'visitor-counter'));
        // dd(config());
    }

        /**
     * Get the absolute path to some package resource.
     *
     * @param  string  $path  The relative path to the resource
     * @return string
     */
    private function packagePath($path)
    {
        return __DIR__ . "/../$path";
    }
}
