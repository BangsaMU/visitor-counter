<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Events\RecordingToggled;
use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\RecordingSwitch;
use Bangsamu\VisitorCounter\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Factories\UserFactory;

final class RecordingSwitchTest extends TestCase
{
    public function test_disabled_switch_writes_nothing_to_the_database_and_injects_no_script(): void
    {
        app(RecordingSwitch::class)->disable('uji');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.16.212'])
            ->get('/halaman')
            ->assertOk()
            ->assertDontSee('api.ipify.org', false);

        $this->assertSame([], $queries);
        $this->assertSame(0, Visitor::query()->count());

        app(RecordingSwitch::class)->enable();
        $this->get('/halaman')->assertOk();
        $this->assertSame(1, Visitor::query()->count());
    }

    public function test_dashboard_toggle_switches_recording_and_dispatches_event(): void
    {
        Event::fake([RecordingToggled::class]);
        Gate::define('viewVisitorCounter', fn (): bool => true);
        $user = UserFactory::new()->create(['name' => 'Budi Uji']);

        $this->actingAs($user)->post(route('visitor-counter.toggle'), ['enabled' => '0'])
            ->assertRedirect(route('visitor-counter.index'))
            ->assertSessionHas('visitor_counter_status');

        $this->assertFalse(app(RecordingSwitch::class)->enabled());
        $this->assertStringContainsString('Budi Uji', (string) app(RecordingSwitch::class)->disabledInfo()['by']);
        Event::assertDispatched(RecordingToggled::class, fn (RecordingToggled $event): bool => ! $event->enabled
            && $event->user?->is($user)
            && $event->source === RecordingToggled::SOURCE_DASHBOARD);

        $this->actingAs($user)->get('/visitor-counter')
            ->assertOk()
            ->assertSee('Pencatatan nonaktif')
            ->assertSee('Aktifkan pencatatan')
            ->assertSee('Budi Uji');

        $this->actingAs($user)->post(route('visitor-counter.toggle'), ['enabled' => '1'])->assertRedirect();
        $this->assertTrue(app(RecordingSwitch::class)->enabled());
        Event::assertDispatched(RecordingToggled::class, fn (RecordingToggled $event): bool => $event->enabled);
    }

    public function test_toggle_requires_dashboard_gate(): void
    {
        $this->actingAs(UserFactory::new()->create())
            ->post(route('visitor-counter.toggle'), ['enabled' => '0'])
            ->assertForbidden();

        $this->assertTrue(app(RecordingSwitch::class)->enabled());
    }

    public function test_config_disabled_cannot_be_enabled_from_dashboard(): void
    {
        config(['visitor-counter.enabled' => false]);
        Gate::define('viewVisitorCounter', fn (): bool => true);
        $user = UserFactory::new()->create();

        $this->actingAs($user)->post(route('visitor-counter.toggle'), ['enabled' => '1'])
            ->assertRedirect(route('visitor-counter.index'))
            ->assertSessionHas('visitor_counter_error');

        $this->actingAs($user)->get('/visitor-counter')
            ->assertOk()
            ->assertSee('VISITOR_COUNTER_ENABLED=false')
            ->assertDontSee('Aktifkan pencatatan');
    }

    public function test_artisan_disable_and_enable(): void
    {
        $this->artisan('visitor-counter:disable')->assertSuccessful();
        $this->assertFalse(app(RecordingSwitch::class)->enabled());
        $this->assertSame('artisan', app(RecordingSwitch::class)->disabledInfo()['by']);

        $this->artisan('visitor-counter:enable')->assertSuccessful();
        $this->assertTrue(app(RecordingSwitch::class)->enabled());
        $this->assertNull(app(RecordingSwitch::class)->disabledInfo());
    }
}
