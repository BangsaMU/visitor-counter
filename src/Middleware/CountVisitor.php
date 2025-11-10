<?php

namespace Bangsamu\VisitorCounter\Middleware;

use Closure;
use Bangsamu\VisitorCounter\Models\Visitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CountVisitor
{
    public function handle($request, Closure $next)
    {
        // Eksekusi request dulu supaya auth sudah diproses
        $response = $next($request);

        // =============================
        // 1️⃣ Dapatkan IP Asli (Real Client IP)
        // =============================

        $ip = $this->getClientPublicIp($request);
        $userAgent = substr($request->header('User-Agent'), 0, 255);
        $path      = $request->path();
        $today     = now()->toDateString();
        $userId    = Auth::check() ? Auth::id() : null;

        $mode = config('Visitor-counter.mode', 'unique_daily');
        $cache_time = config('Visitor-counter.cache_time', 10);

        // Cegah hit berulang dalam 10 menit
        $cacheKey = "visitor:{$userId}:{$ip}:{$today}:{$path}";

        if (!Cache::has($cacheKey)) {
            if ($mode === 'unique_daily') {
                // Catat hanya sekali per IP per hari
                if (!Visitor::where('ip', $ip)
                    ->where('visit_date', $today)
                    ->exists()) {
                    Visitor::create([
                        'user_id'    => $userId,
                        'ip'         => $ip,
                        'user_agent' => $userAgent,
                        'path'       => $path,
                        'visit_date' => $today,
                        'referer'    => $request->header('Referer'),
                    ]);
                }
            } elseif ($mode === 'log_all') {
                Visitor::create([
                    'user_id'    => $userId,
                    'ip'         => $ip,
                    'user_agent' => $userAgent,
                    'path'       => $path,
                    'visit_date' => $today,
                    'referer'    => $request->header('Referer'),
                ]);
            }

            Cache::put($cacheKey, true, now()->addMinutes($cache_time));
        }

        return $response;
    }

    /**
     * Ambil IP publik dari request atau fallback dari layanan eksternal
     */
    protected static function getClientPublicIp($request): string
    {
        // 1️⃣ Ambil IP dari header proxy dulu
        $ip = $request->header('X-Forwarded-For')
            ?? $request->header('X-Real-IP')
            ?? $request->ip();

        // 2️⃣ Jika IP private, ambil IP publik server (cache 6 jam)
        if (self::isPrivateIp($ip)) {
            return cache()->remember('server_public_ip', now()->addHours(6), function () {
                try {
                    // 🌩️ Coba ambil dari Cloudflare
                    $response = Http::timeout(3)->get('https://cloudflare.com/cdn-cgi/trace');

                    if ($response->successful()) {
                        preg_match('/ip=([0-9a-fA-F:.]+)/', $response->body(), $matches);
                        if (!empty($matches[1]) && filter_var($matches[1], FILTER_VALIDATE_IP)) {
                            return $matches[1];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Gagal ambil IP publik dari Cloudflare: ' . $e->getMessage());
                }

                // 🌍 Fallback ke ipify jika Cloudflare gagal
                try {
                    $response = Http::timeout(3)->get('https://api.ipify.org');
                    if ($response->successful()) {
                        $publicIp = trim($response->body());
                        if (filter_var($publicIp, FILTER_VALIDATE_IP)) {
                            return $publicIp;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Gagal ambil IP publik dari ipify: ' . $e->getMessage());
                }

                // 🚫 Jika semua gagal
                return '0.0.0.0';
            });
        }

        return $ip;
    }


    /**
     * Cek apakah IP adalah IP lokal/private
     */
    protected static function isPrivateIp($ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
