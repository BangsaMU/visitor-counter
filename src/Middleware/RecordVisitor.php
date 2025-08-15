<?php
namespace Bangsamu\VisitorCounter\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Bangsamu\VisitorCounter\Models\Visitor;

class RecordVisitor
{
    public function handle($request, Closure $next)
    {
        $ip = $request->ip();
        $date = now()->toDateString();

        // Cegah hit berulang dalam 10 menit
        $cacheKey = "visitor:{$ip}:{$date}";
        if (!Cache::has($cacheKey)) {
            Visitor::create([
                'ip'         => $ip,
                'visit_date' => $date,
                'user_id'    => auth()->id(),
                'user_agent' => $request->header('User-Agent'),
                'path'       => $request->path(),
                'referer'    => $request->header('Referer')
            ]);

            Cache::put($cacheKey, true, now()->addMinutes(10));
        }

        return $next($request);
    }
}
