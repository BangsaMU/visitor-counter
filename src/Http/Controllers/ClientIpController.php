<?php

namespace Bangsamu\VisitorCounter\Http\Controllers;

use Bangsamu\VisitorCounter\Support\ClientIpResolver;
use Bangsamu\VisitorCounter\Support\ReportedClientIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Menerima IP publik dari skrip pelapor browser dan menyimpannya di session (tidak menulis database).
 */
final class ClientIpController
{
    public function store(Request $request, ReportedClientIp $reported): Response
    {
        $validated = $request->validate([
            'ip' => ['required', 'string', 'ip', function (string $attribute, mixed $value, Closure $fail): void {
                if (! ClientIpResolver::isPublic((string) $value)) {
                    $fail('IP harus IP publik.');
                }
            }],
        ]);

        $reported->put($request, $validated['ip']);

        return response()->noContent();
    }
}
