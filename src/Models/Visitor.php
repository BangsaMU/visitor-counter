<?php

namespace Bangsamu\VisitorCounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu baris = satu request web yang dicatat RecordVisit. Tabel/koneksi dari config `visitor-counter`.
 *
 * @property int $id
 * @property Carbon|null $visit_date
 * @property int|null $user_id
 * @property string|null $session_hash
 * @property string|null $method
 * @property string|null $path
 * @property string|null $query_string
 * @property string|null $route_name
 * @property int|null $status
 * @property int|null $duration_ms
 * @property bool $is_ajax
 * @property string|null $ip
 * @property string|null $client_ip
 * @property string|null $client_ip_source
 * @property string|null $user_agent
 * @property string|null $referer
 * @property Carbon|null $created_at
 */
class Visitor extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return (string) config('visitor-counter.table', 'visitors');
    }

    public function getConnectionName(): ?string
    {
        $connection = config('visitor-counter.connection');

        return is_string($connection) && $connection !== '' ? $connection : parent::getConnectionName();
    }

    /**
     * Baris lebih tua dari `retention_days` (0 = tidak ada yang dihapus).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('visitor-counter.retention_days', 0);
        $query = static::query()->where('created_at', '<', now()->subDays(max($days, 1))->startOfDay());

        return $days > 0 ? $query : $query->whereRaw('1 = 0');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'user_id' => 'integer',
            'status' => 'integer',
            'duration_ms' => 'integer',
            'is_ajax' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
