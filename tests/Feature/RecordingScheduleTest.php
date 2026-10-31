<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Contracts\HolidayCalendar;
use Bangsamu\VisitorCounter\Events\SettingsUpdated;
use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\VisitorSettings;
use Bangsamu\VisitorCounter\Tests\TestCase;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Factories\UserFactory;

final class RecordingScheduleTest extends TestCase
{
    private const WEDNESDAY = '2026-11-04';

    private const THURSDAY_HOLIDAY = '2026-11-05';

    private const SATURDAY = '2026-11-07';

    public function test_outside_work_hours_mode_records_only_nights_weekends_and_holidays(): void
    {
        app(VisitorSettings::class)->save(['users' => 'outside_work_hours', 'guests' => 'outside_work_hours']);
        app()->instance(HolidayCalendar::class, new class implements HolidayCalendar
        {
            public function isHoliday(CarbonInterface $date): bool
            {
                return $date->toDateString() === '2026-11-05';
            }
        });

        $visitAt = function (string $at): void {
            $this->travelTo(Carbon::parse($at));
            $this->withServerVariables(['REMOTE_ADDR' => '192.168.16.212'])->get('/halaman')->assertOk();
        };

        $visitAt(self::WEDNESDAY.' 10:00');
        $visitAt(self::WEDNESDAY.' 16:59');
        $this->assertSame(0, Visitor::query()->count(), 'Jam kerja tidak dicatat');

        $visitAt(self::WEDNESDAY.' 07:59');
        $visitAt(self::WEDNESDAY.' 17:00');
        $visitAt(self::THURSDAY_HOLIDAY.' 10:00');
        $visitAt(self::SATURDAY.' 10:00');

        $this->assertSame(
            ['07:59', '17:00', '10:00', '10:00'],
            Visitor::query()->orderBy('id')->get()->map(fn (Visitor $visit): string => $visit->created_at->format('H:i'))->all(),
        );
    }

    public function test_reporter_script_is_not_injected_when_request_is_not_recorded(): void
    {
        app(VisitorSettings::class)->save(['guests' => 'outside_work_hours']);
        $this->travelTo(Carbon::parse(self::WEDNESDAY.' 10:00'));

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.16.212'])
            ->get('/halaman')
            ->assertOk()
            ->assertDontSee('api.ipify.org', false);
    }

    public function test_users_and_guests_have_separate_modes(): void
    {
        app(VisitorSettings::class)->save(['users' => 'never', 'guests' => 'always']);

        $this->get('/halaman')->assertOk();
        $this->actingAs(UserFactory::new()->create())->get('/halaman?login=1')->assertOk();

        $this->assertSame([null], Visitor::query()->pluck('user_id')->all());
    }

    public function test_dashboard_saves_settings_dispatches_event_and_retention_drives_prune(): void
    {
        Event::fake([SettingsUpdated::class]);
        Gate::define('viewVisitorCounter', fn (): bool => true);
        $user = UserFactory::new()->create(['name' => 'Budi Uji']);

        $this->actingAs($user)->post(route('visitor-counter.settings'), [
            'users' => 'outside_work_hours',
            'guests' => 'always',
            'work_days' => ['1', '2', '3', '4', '5', '6'],
            'work_start' => '07:30',
            'work_end' => '16:30',
            'holidays' => '0',
            'retention_days' => '30',
        ])->assertRedirect(route('visitor-counter.index'))->assertSessionHas('visitor_counter_status');

        $settings = app(VisitorSettings::class)->all();
        $this->assertSame(['outside_work_hours', [1, 2, 3, 4, 5, 6], '07:30', '16:30', false, 30], [
            $settings['users'], $settings['work_days'], $settings['work_start'], $settings['work_end'], $settings['holidays'], $settings['retention_days'],
        ]);
        Event::assertDispatched(SettingsUpdated::class, fn (SettingsUpdated $event): bool => $event->before['retention_days'] === 180
            && $event->after['retention_days'] === 30
            && $event->user?->is($user));

        $this->actingAs($user)->get('/visitor-counter')->assertOk()->assertSee('Simpan 30 hari')->assertSee('oleh Budi Uji', false);

        DB::table('visitors')->insert([
            ['path' => 'lama', 'ip' => '10.0.0.1', 'visit_date' => now()->subDays(40)->toDateString(), 'created_at' => now()->subDays(40), 'is_ajax' => false],
            ['path' => 'baru', 'ip' => '10.0.0.1', 'visit_date' => now()->subDays(5)->toDateString(), 'created_at' => now()->subDays(5), 'is_ajax' => false],
        ]);
        $this->artisan('model:prune', ['--model' => [Visitor::class]])->assertSuccessful();
        $this->assertNotContains('lama', DB::table('visitors')->pluck('path')->all());
        $this->assertContains('baru', DB::table('visitors')->pluck('path')->all());
    }

    public function test_settings_are_validated_and_require_dashboard_gate(): void
    {
        $user = UserFactory::new()->create();
        $payload = ['users' => 'always', 'guests' => 'always', 'work_start' => '17:00', 'work_end' => '08:00', 'retention_days' => '30'];

        $this->actingAs($user)->post(route('visitor-counter.settings'), $payload)->assertForbidden();

        Gate::define('viewVisitorCounter', fn (): bool => true);
        $this->actingAs($user)->post(route('visitor-counter.settings'), $payload)->assertSessionHasErrors('work_end');
        $this->assertNull(app(VisitorSettings::class)->updatedInfo());
    }
}
