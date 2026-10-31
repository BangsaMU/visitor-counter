<?php

namespace Bangsamu\VisitorCounter;

use Bangsamu\VisitorCounter\Models\Visitor;
use Bangsamu\VisitorCounter\Support\ClientIpResolver;
use Bangsamu\VisitorCounter\Support\RecordingSchedule;
use Bangsamu\VisitorCounter\Support\RecordingSwitch;
use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Menyusun dan menulis satu baris visitor per request. Kegagalan mencatat tidak boleh mengganggu request user.
 */
final class VisitRecorder
{
    public const DURATION_ATTRIBUTE = 'visitor_counter.duration_ms';

    public const REPEAT_ATTRIBUTE = 'visitor_counter.repeat';

    /** Session: tanda tangan GET yang baru dicatat => timestamp, untuk menyaring refresh berulang */
    public const RECENT_SESSION_KEY = 'visitor_counter_recent';

    private const RECENT_LIMIT = 50;

    public function __construct(
        private readonly ClientIpResolver $clientIp,
        private readonly VisitorSchema $schema,
        private readonly RecordingSwitch $switch,
        private readonly RecordingSchedule $schedule,
    ) {}

    /**
     * Durasi sejak request diterima server (REQUEST_TIME_FLOAT) sampai respons selesai dibuat.
     */
    public function measure(Request $request): void
    {
        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        $request->attributes->set(self::DURATION_ATTRIBUTE, max(0, (int) round((microtime(true) - $startedAt) * 1000)));
    }

    /**
     * Tandai GET yang sama (alamat + query, status, user) dari sesi yang sama dalam `repeat_window_seconds` sejak terakhir
     * dicatat. Dipanggil di handle(), sebelum session disimpan; record() di terminate() melewati request bertanda.
     */
    public function markRepeat(Request $request, Response $response): void
    {
        $window = (int) config('visitor-counter.repeat_window_seconds', 0);
        if ($window <= 0 || ! $request->isMethod('GET') || ! $request->hasSession() || ! $this->shouldRecord($request)) {
            return;
        }

        $now = now()->getTimestamp();
        $signature = hash('xxh3', implode('|', [
            $request->path(),
            (string) $this->queryString($request),
            $response->getStatusCode(),
            (string) $request->user(config('visitor-counter.guard'))?->getAuthIdentifier(),
        ]));

        $recent = array_filter(
            (array) $request->session()->get(self::RECENT_SESSION_KEY, []),
            fn ($at): bool => is_int($at) && $now - $at < $window,
        );

        if (isset($recent[$signature])) {
            $request->attributes->set(self::REPEAT_ATTRIBUTE, true);
        } else {
            $recent[$signature] = $now;
        }

        $request->session()->put(self::RECENT_SESSION_KEY, array_slice($recent, -self::RECENT_LIMIT, null, true));
    }

    public function record(Request $request, Response $response): void
    {
        try {
            if ($request->attributes->get(self::REPEAT_ATTRIBUTE) === true || ! $this->shouldRecord($request)) {
                return;
            }

            $row = $this->attributes($request, $response);
            $this->schema->ensure();

            try {
                Visitor::query()->insert($row);
            } catch (QueryException $exception) {
                if (! config('visitor-counter.auto_schema', true)) {
                    throw $exception;
                }

                // Tabel/kolom berubah setelah ditandai lengkap (mis. dihapus manual): lengkapi lalu coba sekali lagi.
                $this->schema->forget();
                $this->schema->sync();
                Visitor::query()->insert($row);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function shouldRecord(Request $request): bool
    {
        if (! $this->switch->enabled()
            || ! in_array($request->method(), (array) config('visitor-counter.methods', []), true)) {
            return false;
        }

        if (! config('visitor-counter.record_ajax', true) && $this->isAjax($request)) {
            return false;
        }

        $paths = (array) config('visitor-counter.except.paths', []);
        if ($paths !== [] && $request->is(...$paths)) {
            return false;
        }

        $routes = (array) config('visitor-counter.except.routes', []);
        if ($routes !== [] && $request->routeIs(...$routes)) {
            return false;
        }

        return $this->schedule->allows($request->user(config('visitor-counter.guard')) !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(Request $request, Response $response): array
    {
        $now = now();
        $client = $this->clientIp->resolve($request);
        $userId = $request->user(config('visitor-counter.guard'))?->getAuthIdentifier();
        $duration = $request->attributes->get(self::DURATION_ATTRIBUTE);

        return [
            'visit_date' => $now->toDateString(),
            'user_id' => is_numeric($userId) ? (int) $userId : null,
            'session_hash' => $request->hasSession() ? hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')) : null,
            'method' => $request->method(),
            'path' => $this->limit($request->path(), 255),
            'query_string' => $this->queryString($request),
            'route_name' => $this->limit($request->route()?->getName(), 191),
            'status' => $response->getStatusCode(),
            'duration_ms' => is_int($duration) ? $duration : null,
            'is_ajax' => $this->isAjax($request),
            'ip' => $request->ip() ?? '0.0.0.0',
            'client_ip' => $client['ip'],
            'client_ip_source' => $client['source'],
            'user_agent' => $this->limit($request->userAgent(), 255),
            'referer' => $this->limit($request->headers->get('referer'), 255),
            'created_at' => $now,
        ];
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    private function queryString(Request $request): ?string
    {
        $hidden = array_map('strtolower', (array) config('visitor-counter.hidden_query_keys', []));
        $query = array_filter(
            $request->query(),
            fn (string|int $key): bool => ! in_array(strtolower((string) $key), $hidden, true),
            ARRAY_FILTER_USE_KEY,
        );

        return $query === [] ? null : $this->limit(urldecode(http_build_query($query)), 1000);
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null || $value === '' ? null : mb_substr($value, 0, $length);
    }
}
