<?php

namespace Bangsamu\VisitorCounter\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP klien yang dicatat: IP yang dilihat server bila publik, atau IP yang dilaporkan browser bila server hanya melihat
 * IP private (di belakang NAT/router tanpa header forwarding). Juga menyisipkan skrip pelapor IP ke halaman HTML.
 */
final class ClientIpResolver
{
    public const SOURCE_SERVER = 'server';

    public const SOURCE_BROWSER = 'browser';

    public const REPORT_ROUTE = 'visitor-counter.client-ip';

    public function __construct(
        private readonly ReportedClientIp $reported,
        private readonly RecordingSwitch $switch,
        private readonly RecordingSchedule $schedule,
    ) {}

    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array{ip: string|null, source: string|null}
     */
    public function resolve(Request $request): array
    {
        $serverIp = $request->ip();
        $mode = $this->mode();

        if ($serverIp !== null && ($mode === 'server' || ($mode === 'auto' && self::isPublic($serverIp)))) {
            return ['ip' => $serverIp, 'source' => self::SOURCE_SERVER];
        }

        $reported = $this->reported->get($request);

        return ['ip' => $reported, 'source' => $reported === null ? null : self::SOURCE_BROWSER];
    }

    /**
     * Browser perlu melapor IP: server tidak melihat IP publik dan laporan di session belum ada / sudah lama.
     */
    public function shouldReport(Request $request): bool
    {
        if (! $this->switch->enabled() || ! $request->hasSession() || ! Route::has(self::REPORT_ROUTE)) {
            return false;
        }

        $mode = $this->mode();
        $serverIp = $request->ip();
        if ($mode === 'server' || ($mode === 'auto' && $serverIp !== null && self::isPublic($serverIp))) {
            return false;
        }

        return $this->reported->isStale($request)
            && $this->schedule->allows($request->user(config('visitor-counter.guard')) !== null);
    }

    /**
     * Skrip pelapor IP, atau string kosong bila tidak perlu melapor.
     */
    public function reporterScript(Request $request): string
    {
        if (! $this->shouldReport($request)) {
            return '';
        }

        return view('visitor-counter::client-ip-script', [
            'lookupUrl' => (string) config('visitor-counter.client_ip.lookup_url', 'https://api.ipify.org?format=json'),
            'reportUrl' => route(self::REPORT_ROUTE, [], false),
            'token' => $request->session()->token(),
            'nonce' => Vite::cspNonce(),
        ])->render();
    }

    /**
     * Sisipkan skrip pelapor sebelum </body> halaman HTML biasa (bukan AJAX, file, atau stream).
     */
    public function injectReporter(Request $request, Response $response): void
    {
        if (! config('visitor-counter.client_ip.inject_script', true)
            || ! $request->isMethod('GET')
            || $request->ajax()
            || ! $response instanceof HttpResponse
            || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');
        if ($position === false) {
            return;
        }

        $script = $this->reporterScript($request);
        if ($script === '') {
            return;
        }

        // setContent() menimpa `original` (View/array): kembalikan supaya assertViewHas/viewData app tetap berlaku.
        $original = $response->original;
        $response->setContent(substr_replace($content, $script, $position, 0));
        $response->original = $original;
        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
        }
    }

    private function mode(): string
    {
        $mode = (string) config('visitor-counter.client_ip.mode', 'auto');

        return in_array($mode, ['auto', 'server', 'browser'], true) ? $mode : 'auto';
    }
}
