<?php

namespace Bangsamu\VisitorCounter;

use Bangsamu\VisitorCounter\Models\Visitor;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Angka ringkas kunjungan (facade VisitorCounter). "Unik" dihitung per IP klien, atau IP server bila IP klien kosong.
 */
final class VisitorStats
{
    private const UNIQUE_IP = 'COUNT(DISTINCT COALESCE(client_ip, ip))';

    public function total(): int
    {
        return Visitor::query()->count();
    }

    public function today(): int
    {
        return Visitor::query()->where('visit_date', today()->toDateString())->count();
    }

    public function monthly(): int
    {
        return Visitor::query()
            ->whereBetween('visit_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();
    }

    public function unique(): int
    {
        return (int) Visitor::query()->toBase()->selectRaw(self::UNIQUE_IP.' as aggregate')->value('aggregate');
    }

    public function uniqueToday(): int
    {
        return (int) Visitor::query()->toBase()
            ->where('visit_date', today()->toDateString())
            ->selectRaw(self::UNIQUE_IP.' as aggregate')
            ->value('aggregate');
    }

    /**
     * Per hari: jumlah request, IP unik, user unik.
     *
     * @return Collection<int, array{date: string, hits: int, unique_ips: int, unique_users: int}>
     */
    public function daily(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Visitor::query()->toBase()
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('visit_date, COUNT(*) as hits, '.self::UNIQUE_IP.' as unique_ips, COUNT(DISTINCT user_id) as unique_users')
            ->groupBy('visit_date')
            ->orderBy('visit_date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => substr((string) $row->visit_date, 0, 10),
                'hits' => (int) $row->hits,
                'unique_ips' => (int) $row->unique_ips,
                'unique_users' => (int) $row->unique_users,
            ]);
    }
}
