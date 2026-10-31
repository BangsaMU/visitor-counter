<?php

namespace Bangsamu\VisitorCounter\Http\Middleware;

use Bangsamu\VisitorCounter\Support\ClientIpResolver;
use Bangsamu\VisitorCounter\VisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat request ke tabel visitor. Baris ditulis di terminate(), setelah respons terkirim (PHP-FPM
 * fastcgi_finish_request), jadi user tidak ikut menunggu insert.
 */
final class RecordVisit
{
    public function __construct(
        private readonly VisitRecorder $recorder,
        private readonly ClientIpResolver $clientIp,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->recorder->measure($request);
        $this->recorder->markRepeat($request, $response);
        $this->clientIp->injectReporter($request, $response);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->recorder->record($request, $response);
    }
}
