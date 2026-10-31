<?php

namespace Bangsamu\VisitorCounter\Http\Controllers;

use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\DashboardLayout;
use Bangsamu\VisitorCounter\Support\UserDirectory;
use Bangsamu\VisitorCounter\Support\VisitFilters;
use Bangsamu\VisitorCounter\VisitorStats;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DashboardController
{
    public function index(Request $request, UserDirectory $users): View
    {
        Gate::authorize((string) config('visitor-counter.dashboard.gate', 'viewVisitorCounter'));

        $filters = VisitFilters::fromRequest($request);
        $userIds = $filters->user === '' ? null : $users->search($filters->user);
        $query = fn (): Builder => $filters->apply(Visitor::query(), $userIds);

        $summary = $query()->toBase()
            ->selectRaw('COUNT(*) as hits, COUNT(DISTINCT user_id) as users, COUNT(DISTINCT COALESCE(client_ip, ip)) as ips, AVG(duration_ms) as avg_ms')
            ->first();

        $topTargets = $query()->toBase()
            ->selectRaw('COALESCE(route_name, path) as target, COUNT(*) as hits')
            ->groupByRaw('COALESCE(route_name, path)')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $topUsers = $query()->toBase()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as hits, MAX(created_at) as last_seen')
            ->groupBy('user_id')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $visits = $query()
            ->orderByDesc('id')
            ->paginate(max(10, (int) config('visitor-counter.dashboard.per_page', 50)))
            ->withQueryString();

        return view('visitor-counter::dashboard', [
            'layout' => DashboardLayout::resolve(),
            'filters' => $filters,
            'summary' => $summary,
            'topTargets' => $topTargets,
            'topUsers' => $topUsers,
            'visits' => $visits,
            'userLabels' => $users->labels([...$topUsers->pluck('user_id'), ...$visits->getCollection()->pluck('user_id')]),
        ]);
    }

    /**
     * Per hari dalam rentang filter: jumlah request, IP unik, user unik.
     */
    public function stats(Request $request, VisitorStats $stats): JsonResponse
    {
        Gate::authorize((string) config('visitor-counter.dashboard.gate', 'viewVisitorCounter'));

        $filters = VisitFilters::fromRequest($request);

        return response()->json([
            'from' => $filters->from->toDateString(),
            'to' => $filters->to->toDateString(),
            'days' => $stats->daily($filters->from, $filters->to),
        ]);
    }

    public function today(VisitorStats $stats): JsonResponse
    {
        Gate::authorize((string) config('visitor-counter.dashboard.gate', 'viewVisitorCounter'));

        return response()->json([
            'total_hits' => $stats->today(),
            'unique_visitors' => $stats->uniqueToday(),
        ]);
    }
}
