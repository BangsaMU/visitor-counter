<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Tests\TestCase;

final class ClientIpTest extends TestCase
{
    private const NAT_IP = '192.168.16.212';

    private const PUBLIC_IP = '36.68.10.20';

    public function test_public_server_ip_is_the_client_ip_and_no_reporter_is_injected(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::PUBLIC_IP])
            ->get('/halaman')
            ->assertOk()
            ->assertDontSee('api.ipify.org', false);

        $this->assertVisitIps(self::PUBLIC_IP, self::PUBLIC_IP, 'server');
    }

    public function test_behind_nat_the_browser_reported_ip_is_used(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])
            ->withSession(['visitor_counter_client_ip' => ['ip' => self::PUBLIC_IP, 'at' => now()->getTimestamp()]])
            ->get('/halaman')
            ->assertOk()
            ->assertDontSee('api.ipify.org', false);

        $this->assertVisitIps(self::NAT_IP, self::PUBLIC_IP, 'browser');
    }

    public function test_ip_reported_by_the_app_under_a_fallback_session_key_is_used(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])
            ->withSession(['reported_client_ip' => ['ip' => self::PUBLIC_IP, 'at' => now()->getTimestamp()]])
            ->get('/halaman')
            ->assertOk();

        $this->assertVisitIps(self::NAT_IP, self::PUBLIC_IP, 'browser');
    }

    public function test_reporter_script_is_injected_before_body_end_while_ip_is_unknown_or_stale(): void
    {
        $content = (string) $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])
            ->withSession(['visitor_counter_client_ip' => ['ip' => self::PUBLIC_IP, 'at' => now()->subHour()->getTimestamp()]])
            ->get('/halaman')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('api.ipify.org', $content);
        $this->assertStringContainsString(json_encode(route('visitor-counter.client-ip', [], false)), $content);
        $this->assertLessThan(strripos($content, '</body>'), strpos($content, 'api.ipify.org'));
    }

    public function test_injection_keeps_the_view_response_for_view_assertions(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])
            ->get('/tampilan')
            ->assertOk()
            ->assertSee('api.ipify.org', false)
            ->assertViewIs('visitor-counter::layout');
    }

    public function test_server_mode_records_the_server_ip_without_reporter(): void
    {
        config(['visitor-counter.client_ip.mode' => 'server']);

        $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])
            ->get('/halaman')
            ->assertOk()
            ->assertDontSee('api.ipify.org', false);

        $this->assertVisitIps(self::NAT_IP, self::NAT_IP, 'server');
    }

    public function test_unknown_browser_ip_is_left_empty(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::NAT_IP])->get('/halaman')->assertOk();

        $this->assertVisitIps(self::NAT_IP, null, null);
    }

    public function test_report_endpoint_stores_public_ip_in_session_and_rejects_private_ip(): void
    {
        $this->postJson(route('visitor-counter.client-ip'), ['ip' => self::PUBLIC_IP])->assertNoContent();
        $this->assertSame(self::PUBLIC_IP, session('visitor_counter_client_ip')['ip'] ?? null);

        $this->postJson(route('visitor-counter.client-ip'), ['ip' => '10.1.2.3'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ip');

        $this->assertSame(0, Visitor::query()->count());
    }

    private function assertVisitIps(?string $ip, ?string $clientIp, ?string $source): void
    {
        $visit = Visitor::query()->sole();

        $this->assertSame([$ip, $clientIp, $source], [$visit->ip, $visit->client_ip, $visit->client_ip_source]);
    }
}
