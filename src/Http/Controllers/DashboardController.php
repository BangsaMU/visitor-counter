<?php

namespace Bangsamu\VisitorCounter\Http\Controllers;

use Bangsamu\VisitorCounter\Contracts\HolidayCalendar;
use Bangsamu\VisitorCounter\Events\RecordingToggled;
use Bangsamu\VisitorCounter\Events\SettingsUpdated;
use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\DashboardLayout;
use Bangsamu\VisitorCounter\Support\NoHolidays;
use Bangsamu\VisitorCounter\Support\RecordingSchedule;
use Bangsamu\VisitorCounter\Support\RecordingSwitch;
use Bangsamu\VisitorCounter\Support\UserDirectory;
use Bangsamu\VisitorCounter\Support\VisitFilters;
use Bangsamu\VisitorCounter\Support\VisitorSettings;
use Bangsamu\VisitorCounter\VisitorStats;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class DashboardController
{
    public function index(Request $request, UserDirectory $users, RecordingSwitch $switch, VisitorSettings $settings, RecordingSchedule $schedule): View
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

        // Cursor (keyset id): tanpa COUNT(*) dan OFFSET, jadi halaman berikutnya tetap cepat di tabel besar
        $visits = $query()
            ->orderByDesc('id')
            ->cursorPaginate(max(10, (int) config('visitor-counter.dashboard.per_page', 50)))
            ->withQueryString();

        return view('visitor-counter::dashboard', [
            'layout' => DashboardLayout::resolve(),
            'recording' => [
                'enabled' => $switch->enabled(),
                'config_enabled' => $switch->configEnabled(),
                'disabled' => $switch->disabledInfo(),
            ],
            'recordingSettings' => $settings->all(),
            'recordingSettingsUpdated' => $settings->updatedInfo(),
            'recordingNow' => [
                'work_time' => $schedule->isWorkTime(now()),
                'users' => $schedule->allows(true),
                'guests' => $schedule->allows(false),
            ],
            'holidaysAvailable' => ! app(HolidayCalendar::class) instanceof NoHolidays,
            'filters' => $filters,
            'summary' => $summary,
            'topTargets' => $topTargets,
            'topUsers' => $topUsers,
            'visits' => $visits,
            'userLabels' => $users->labels([...$topUsers->pluck('user_id'), ...$visits->getCollection()->pluck('user_id')]),
        ]);
    }

    /**
     * Tombol Nonaktifkan/Aktifkan: saat nonaktif tidak ada request yang disimpan ke database.
     */
    public function toggle(Request $request, RecordingSwitch $switch, UserDirectory $users): RedirectResponse
    {
        Gate::authorize((string) config('visitor-counter.dashboard.gate', 'viewVisitorCounter'));

        $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = $request->boolean('enabled');
        $redirect = redirect()->route('visitor-counter.index');

        if (! $switch->configEnabled()) {
            return $redirect->with('visitor_counter_error', 'Pencatatan dimatikan lewat konfigurasi (VISITOR_COUNTER_ENABLED=false), tidak bisa diubah dari sini.');
        }

        $user = $request->user();
        $enabled ? $switch->enable() : $switch->disable($users->labelFor($user));
        event(new RecordingToggled($enabled, $user, RecordingToggled::SOURCE_DASHBOARD));

        return $redirect->with('visitor_counter_status', $enabled
            ? 'Pencatatan visitor diaktifkan.'
            : 'Pencatatan visitor dinonaktifkan: request tidak disimpan ke database sampai diaktifkan lagi.');
    }

    /**
     * Pengaturan pencatatan: kapan user login / tamu dicatat, jam & hari kerja, hari libur, retensi.
     */
    public function saveSettings(Request $request, VisitorSettings $settings, UserDirectory $users): RedirectResponse
    {
        Gate::authorize((string) config('visitor-counter.dashboard.gate', 'viewVisitorCounter'));

        $validated = $request->validate([
            'users' => ['required', Rule::in(VisitorSettings::MODES)],
            'guests' => ['required', Rule::in(VisitorSettings::MODES)],
            'work_days' => ['array'],
            'work_days.*' => ['integer', 'between:1,7'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
            'holidays' => ['boolean'],
            'retention_days' => ['required', 'integer', 'between:0,3650'],
        ]);

        $before = $settings->all();
        $user = $request->user();
        // checkbox yang tidak dicentang tidak terkirim: tanpa hari kerja / tanpa hari libur
        $after = $settings->save($validated + ['work_days' => [], 'holidays' => false], $users->labelFor($user));

        if ($after !== $before) {
            event(new SettingsUpdated($before, $after, $user));
        }

        return redirect()->route('visitor-counter.index')->with('visitor_counter_status', 'Pengaturan pencatatan disimpan.');
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
