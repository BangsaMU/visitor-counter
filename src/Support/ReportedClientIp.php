<?php

namespace Bangsamu\VisitorCounter\Support;

use Illuminate\Http\Request;

/**
 * IP publik user menurut browser (dilaporkan skrip pelapor), disimpan di session sebagai ['ip' => ..., 'at' => timestamp].
 * Juga membaca key session pelapor milik app (`client_ip.fallback_session_keys`) dengan format yang sama.
 */
final class ReportedClientIp
{
    public function get(Request $request): ?string
    {
        return $this->latest($request)['ip'] ?? null;
    }

    public function put(Request $request, string $ip): void
    {
        $request->session()->put($this->sessionKey(), ['ip' => $ip, 'at' => now()->getTimestamp()]);
    }

    /**
     * Belum ada laporan, atau laporan terakhir sudah lebih tua dari `client_ip.refresh_seconds`.
     */
    public function isStale(Request $request): bool
    {
        $latest = $this->latest($request);
        $refresh = max(60, (int) config('visitor-counter.client_ip.refresh_seconds', 1800));

        return $latest === null || now()->getTimestamp() - $latest['at'] >= $refresh;
    }

    /**
     * @return array{ip: string, at: int}|null
     */
    private function latest(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $latest = null;
        foreach ($this->sessionKeys() as $key) {
            $entry = $request->session()->get($key);
            if (! is_array($entry) || ! is_string($entry['ip'] ?? null) || ! ClientIpResolver::isPublic($entry['ip'])) {
                continue;
            }

            $at = (int) ($entry['at'] ?? 0);
            if ($latest === null || $at > $latest['at']) {
                $latest = ['ip' => $entry['ip'], 'at' => $at];
            }
        }

        return $latest;
    }

    private function sessionKey(): string
    {
        return (string) config('visitor-counter.client_ip.session_key', 'visitor_counter_client_ip');
    }

    /**
     * @return list<string>
     */
    private function sessionKeys(): array
    {
        $keys = [$this->sessionKey(), ...(array) config('visitor-counter.client_ip.fallback_session_keys', [])];

        return array_values(array_unique(array_filter($keys, fn ($key): bool => is_string($key) && $key !== '')));
    }
}
