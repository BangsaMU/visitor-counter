<?php

namespace Bangsamu\VisitorCounter\Tests;

use Bangsamu\VisitorCounter\VisitorCounterServiceProvider;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;

#[WithMigration]
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [VisitorCounterServiceProvider::class];
    }

    protected string $switchPath;

    protected string $settingsPath;

    protected function tearDown(): void
    {
        @unlink($this->switchPath);
        @unlink($this->settingsPath);

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $prefix = sys_get_temp_dir().'/visitor-counter-test-'.bin2hex(random_bytes(6));
        $this->switchPath = $prefix.'-disabled.json';
        $this->settingsPath = $prefix.'-settings.json';
        $app['config']->set('visitor-counter.switch_path', $this->switchPath);
        $app['config']->set('visitor-counter.settings_path', $this->settingsPath);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * @param  Router  $router
     */
    protected function defineWebRoutes($router): void
    {
        $router->get('halaman', fn () => response('<html><body><p>Halaman</p></body></html>'))->name('halaman');
        $router->get('tampilan', fn () => view('visitor-counter::layout'))->name('tampilan');
        $router->post('simpan', fn () => response()->json(['ok' => true]))->name('simpan');
        $router->get('session/status', fn () => response()->json(['ok' => true]))->name('session.status');
        $router->get('rusak', fn () => abort(500))->name('rusak');
    }
}
