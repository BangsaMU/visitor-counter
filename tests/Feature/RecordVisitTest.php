<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Tests\TestCase;
use Orchestra\Testbench\Factories\UserFactory;

final class RecordVisitTest extends TestCase
{
    public function test_get_request_is_recorded_with_route_status_duration_and_sanitized_query(): void
    {
        $this->get('/halaman?tab=riwayat&token=rahasia')->assertOk();

        $visit = Visitor::query()->sole();
        $this->assertSame('GET', $visit->method);
        $this->assertSame('halaman', $visit->path);
        $this->assertSame('halaman', $visit->route_name);
        $this->assertSame(200, $visit->status);
        $this->assertSame('tab=riwayat', $visit->query_string);
        $this->assertIsInt($visit->duration_ms);
        $this->assertFalse($visit->is_ajax);
        $this->assertSame('127.0.0.1', $visit->ip);
        $this->assertSame(today()->toDateString(), $visit->visit_date?->toDateString());
        $this->assertSame(64, strlen((string) $visit->session_hash));
        $this->assertNull($visit->user_id);
    }

    public function test_logged_in_ajax_post_and_failed_request_are_recorded_for_the_user(): void
    {
        $user = UserFactory::new()->create();

        $this->actingAs($user)->postJson('/simpan')->assertOk();
        $this->actingAs($user)->get('/rusak')->assertStatus(500);

        $visits = Visitor::query()->orderBy('id')->get();
        $this->assertSame(
            [['POST', 200, true, $user->getKey()], ['GET', 500, false, $user->getKey()]],
            $visits->map(fn (Visitor $visit): array => [$visit->method, $visit->status, $visit->is_ajax, $visit->user_id])->all(),
        );
    }

    public function test_refreshing_the_same_page_is_recorded_once_per_window(): void
    {
        $this->get('/halaman')->assertOk();
        $this->get('/halaman')->assertOk();
        $this->getJson('/halaman')->assertOk();
        $this->assertSame(1, Visitor::query()->count());

        $this->get('/halaman?tab=lain')->assertOk();
        $this->assertSame(2, Visitor::query()->count(), 'Query berbeda = halaman berbeda');

        $this->travel(61)->seconds();
        $this->get('/halaman')->assertOk();
        $this->assertSame(3, Visitor::query()->count(), 'Setelah jendela lewat dicatat lagi');
    }

    public function test_repeated_actions_and_disabled_window_are_always_recorded(): void
    {
        $this->postJson('/simpan')->assertOk();
        $this->postJson('/simpan')->assertOk();
        $this->assertSame(2, Visitor::query()->count());

        config(['visitor-counter.repeat_window_seconds' => 0]);
        $this->get('/halaman')->assertOk();
        $this->get('/halaman')->assertOk();
        $this->assertSame(4, Visitor::query()->count());
    }

    public function test_excluded_routes_and_methods_are_not_recorded(): void
    {
        $this->get('/session/status')->assertOk();
        $this->call('HEAD', '/halaman')->assertOk();

        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_nothing_is_recorded_when_disabled_or_ajax_recording_is_off(): void
    {
        config(['visitor-counter.record_ajax' => false]);
        $this->getJson('/halaman')->assertOk();

        config(['visitor-counter.enabled' => false]);
        $this->get('/halaman')->assertOk();

        $this->assertSame(0, Visitor::query()->count());
    }
}
