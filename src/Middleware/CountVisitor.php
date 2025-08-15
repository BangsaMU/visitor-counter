<?php

namespace Bangsamu\VisitorCounter\Middleware;

use Closure;
use Bangsamu\VisitorCounter\Models\Visitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CountVisitor
{
    public function handle($request, Closure $next)
    {
        // Eksekusi request dulu supaya auth sudah diproses
        $response = $next($request);

        $ip        = $request->ip();
        $userAgent = substr($request->header('User-Agent'), 0, 255);
        $path      = $request->path();
        $today     = now()->toDateString();
        $userId    = Auth::check() ? Auth::id() : null;

        $mode = config('Visitor-counter.mode', 'unique_daily');

        // Cegah hit berulang dalam 10 menit
        $cacheKey = "visitor:{$ip}:{$today}";

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
                        'referer'    => $request->header('Referer')
                    ]);
                }
            } elseif ($mode === 'log_all') {
                // Catat semua request
                Visitor::create([
                    'user_id'    => $userId,
                    'ip'         => $ip,
                    'user_agent' => $userAgent,
                    'path'       => $path,
                    'visit_date' => $today,
                    'referer'    => $request->header('Referer')
                ]);
            }
            Cache::put($cacheKey, true, now()->addMinutes(10));
        }

        return $response;
    }
}
