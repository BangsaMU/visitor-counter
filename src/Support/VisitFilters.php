<?php

namespace Bangsamu\VisitorCounter\Support;

use Bangsamu\VisitorCounter\Models\Visitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

/**
 * Filter dashboard dari query string. Rentang tanggal dibatasi `dashboard.max_range_days` supaya query tetap ringan.
 */
final class VisitFilters
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const STATUSES = ['2xx', '3xx', '4xx', '5xx'];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $user,
        public readonly string $ip,
        public readonly string $target,
        public readonly string $method,
        public readonly string $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $maxDays = max(1, (int) config('visitor-counter.dashboard.max_range_days', 92));
        $to = self::date($request->query('to')) ?? CarbonImmutable::today();
        $from = self::date($request->query('from')) ?? $to->subDays(6);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= $maxDays) {
            $from = $to->subDays($maxDays - 1);
        }

        $method = strtoupper(self::text($request, 'method'));
        $status = self::text($request, 'status');

        return new self(
            $from,
            $to,
            self::text($request, 'user'),
            self::text($request, 'ip'),
            self::text($request, 'target'),
            in_array($method, self::METHODS, true) ? $method : '',
            in_array($status, self::STATUSES, true) ? $status : '',
        );
    }

    /**
     * @param  Builder<Visitor>  $query
     * @param  list<int>|null  $userIds  null = tidak memfilter user
     * @return Builder<Visitor>
     */
    public function apply(Builder $query, ?array $userIds): Builder
    {
        $query->whereBetween('created_at', [$this->from->startOfDay(), $this->to->endOfDay()]);

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }
        if ($this->ip !== '') {
            $query->where(fn (Builder $query) => $query->where('ip', $this->ip)->orWhere('client_ip', $this->ip));
        }
        if ($this->target !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('path', 'like', '%'.$this->target.'%')
                ->orWhere('route_name', 'like', '%'.$this->target.'%'));
        }
        if ($this->method !== '') {
            $query->where('method', $this->method);
        }
        if ($this->status !== '') {
            $base = (int) $this->status[0] * 100;
            $query->whereBetween('status', [$base, $base + 99]);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function methods(): array
    {
        return self::METHODS;
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return self::STATUSES;
    }

    private static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, 191) : '';
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
