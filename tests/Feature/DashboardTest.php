<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Facades\VisitorCounter;
use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Tests\TestCase;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Factories\UserFactory;

final class DashboardTest extends TestCase
{
    public function test_guest_and_user_without_ability_cannot_open_dashboard(): void
    {
        $this->getJson('/visitor-counter')->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create())->get('/visitor-counter')->assertForbidden();
    }

    public function test_default_gate_allows_user_with_configured_ability(): void
    {
        $root = UserFactory::new()->create(['email' => 'root@example.test']);
        Gate::define('is_root', fn ($user): bool => $user->email === 'root@example.test');

        $this->actingAs($root)->get('/visitor-counter')->assertOk()->assertSee('Visitor Log');
    }

    public function test_allowed_user_sees_visits_with_user_label_filters_and_daily_stats(): void
    {
        Gate::define('viewVisitorCounter', fn (): bool => true);
        $user = UserFactory::new()->create(['name' => 'Budi Uji']);

        $this->actingAs($user)->get('/halaman?tab=satu')->assertOk();

        $this->actingAs($user)->get('/visitor-counter')
            ->assertOk()
            ->assertSee('/halaman')
            ->assertSee('tab=satu')
            ->assertSee('Budi Uji');

        $this->actingAs($user)->get('/visitor-counter?target=rusak')
            ->assertOk()
            ->assertDontSee('tab=satu');

        $this->actingAs($user)->getJson('/visitor-counter/stats')
            ->assertOk()
            ->assertJsonPath('days.0.date', today()->toDateString())
            ->assertJsonPath('days.0.unique_users', 1);
    }

    public function test_visit_list_uses_cursor_pagination(): void
    {
        Gate::define('viewVisitorCounter', fn (): bool => true);
        $user = UserFactory::new()->create();
        DB::table('visitors')->insert(collect(range(1, 12))->map(fn (int $i): array => [
            'path' => 'hal-'.$i,
            'ip' => '10.0.0.1',
            'visit_date' => today()->toDateString(),
            'created_at' => now(),
            'is_ajax' => false,
        ])->all());
        config(['visitor-counter.dashboard.per_page' => 10]);

        $html = (string) $this->actingAs($user)->get('/visitor-counter')->assertOk()->getContent();
        preg_match('/href="([^"]*cursor=[^"]*)"/', $html, $next);

        $this->assertNotEmpty($next, 'Link halaman berikutnya memakai cursor');
        $this->assertStringContainsString('/hal-12', $html);
        $this->assertStringNotContainsString('/hal-1<', $html);

        $this->actingAs($user)->get(html_entity_decode($next[1]))
            ->assertOk()
            ->assertSee('/hal-1');
    }

    public function test_facade_counts_and_retention_prune(): void
    {
        config(['visitor-counter.retention_days' => 30]);
        $row = fn (string $path, string $ip, $createdAt): array => [
            'path' => $path,
            'ip' => $ip,
            'visit_date' => $createdAt->toDateString(),
            'created_at' => $createdAt,
            'is_ajax' => false,
        ];
        DB::table('visitors')->insert([
            $row('lama', '10.0.0.1', now()->subDays(40)),
            $row('baru-1', '10.0.0.2', now()),
            $row('baru-2', '10.0.0.2', now()),
        ]);

        $this->assertSame(3, VisitorCounter::total());
        $this->assertSame(2, VisitorCounter::today());
        $this->assertSame(1, VisitorCounter::uniqueToday());
        $this->assertSame(2, VisitorCounter::unique());

        $this->artisan('model:prune', ['--model' => [Visitor::class]])->assertSuccessful();
        $this->assertSame(['baru-1', 'baru-2'], DB::table('visitors')->orderBy('id')->pluck('path')->all());

        config(['visitor-counter.retention_days' => 0]);
        DB::table('visitors')->update(['created_at' => now()->subYears(2)]);
        $this->artisan('model:prune', ['--model' => [Visitor::class]])->assertSuccessful();
        $this->assertSame(2, DB::table('visitors')->count());
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'Visitor'));

        $this->assertCount(1, $events);
        $this->assertSame('30 2 * * *', $events->first()->expression);
    }
}
