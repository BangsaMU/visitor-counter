<?php

namespace Bangsamu\VisitorCounter\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Skema tabel visitor yang membentuk dirinya sendiri: tabel yang belum ada dibuat, tabel yang sudah ada (v0.0.x atau
 * buatan app) hanya ditambah kolom/index yang kurang, jadi tidak pernah gagal karena tabel/kolom sudah ada. Dipakai
 * migration paket, command `visitor-counter:sync-schema`, dan pencatat sebelum insert (sekali per versi skema, ditandai
 * di cache), sehingga app cukup `composer install`.
 */
final class VisitorSchema
{
    /** Naikkan setiap ada kolom/index baru supaya pemeriksaan otomatis diulang sekali. */
    public const VERSION = 1;

    private bool $ensured = false;

    /**
     * Pastikan skema lengkap; pemeriksaan ke database hanya sekali per versi skema (cache), bukan setiap request.
     */
    public function ensure(): void
    {
        if ($this->ensured || ! config('visitor-counter.auto_schema', true)) {
            return;
        }

        if (! $this->isMarked()) {
            $this->sync();
        }

        $this->ensured = true;
    }

    /**
     * Buat tabel bila belum ada, atau tambahkan kolom/index yang kurang.
     *
     * @return list<string> perubahan yang dilakukan (kosong = skema sudah lengkap)
     */
    public function sync(): array
    {
        $schema = Schema::connection($this->connection());
        $table = $this->table();

        if (! $schema->hasTable($table)) {
            $schema->create($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->id();
                foreach (self::columns() as $define) {
                    $define($blueprint);
                }
                foreach (self::indexes() as $columns) {
                    $blueprint->index($columns, $this->indexName($table, $columns));
                }
            });
            $this->mark();

            return ['create table '.$table];
        }

        $changes = [];
        $existing = array_map('strtolower', $schema->getColumnListing($table));
        $missing = array_diff_key(self::columns(), array_flip($existing));

        if ($missing !== []) {
            $schema->table($table, function (Blueprint $blueprint) use ($missing): void {
                foreach ($missing as $define) {
                    // Tabel bisa sudah berisi data: kolom tambahan tanpa default dibuat boleh kosong.
                    $column = $define($blueprint);
                    if ($column instanceof ColumnDefinition && $column->get('default') === null) {
                        $column->nullable();
                    }
                }
            });
            foreach (array_keys($missing) as $column) {
                $changes[] = 'add column '.$column;
            }
        }

        foreach (self::indexes() as $columns) {
            if (! $schema->hasIndex($table, $columns)) {
                $schema->table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $this->indexName($table, $columns)));
                $changes[] = 'add index '.implode(', ', $columns);
            }
        }

        $this->mark();

        return $changes;
    }

    /**
     * Lupakan tanda "skema lengkap" (mis. tabel dihapus manual setelah ditandai) supaya diperiksa ulang.
     */
    public function forget(): void
    {
        $this->ensured = false;

        try {
            Cache::forget($this->cacheKey());
        } catch (Throwable) {
            // Cache tidak tersedia: tanda memang tidak tersimpan.
        }
    }

    public function table(): string
    {
        return (string) config('visitor-counter.table', 'visitors');
    }

    public function connection(): ?string
    {
        $connection = config('visitor-counter.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    /**
     * Kolom selain `id`. Definisi kolom v0.0.x (`ip`, `visit_date`, `updated_at`, dst.) dipertahankan sama persis supaya
     * tabel yang dibuat baru identik dengan tabel v0.0.x / buatan app yang dilengkapi.
     *
     * @return array<string, Closure(Blueprint): ColumnDefinition>
     */
    private static function columns(): array
    {
        return [
            'ip' => fn (Blueprint $table) => $table->string('ip', 45),
            'visit_date' => fn (Blueprint $table) => $table->date('visit_date'),
            'user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable(),
            'session_hash' => fn (Blueprint $table) => $table->string('session_hash', 64)->nullable(),
            'method' => fn (Blueprint $table) => $table->string('method', 10)->nullable(),
            'path' => fn (Blueprint $table) => $table->string('path', 255)->nullable(),
            'query_string' => fn (Blueprint $table) => $table->string('query_string', 1000)->nullable(),
            'route_name' => fn (Blueprint $table) => $table->string('route_name', 191)->nullable(),
            'status' => fn (Blueprint $table) => $table->unsignedSmallInteger('status')->nullable(),
            'duration_ms' => fn (Blueprint $table) => $table->unsignedInteger('duration_ms')->nullable(),
            'is_ajax' => fn (Blueprint $table) => $table->boolean('is_ajax')->default(false),
            'client_ip' => fn (Blueprint $table) => $table->string('client_ip', 45)->nullable(),
            'client_ip_source' => fn (Blueprint $table) => $table->string('client_ip_source', 10)->nullable(),
            'user_agent' => fn (Blueprint $table) => $table->string('user_agent', 255)->nullable(),
            'referer' => fn (Blueprint $table) => $table->string('referer', 255)->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            // v0.0.x memakai timestamps(); tidak diisi lagi (Visitor::UPDATED_AT = null).
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];
    }

    /**
     * @return list<list<string>>
     */
    private static function indexes(): array
    {
        return [
            ['visit_date', 'ip'],
            ['created_at'],
            ['user_id', 'created_at'],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexName(string $table, array $columns): string
    {
        return strtolower($table.'_'.implode('_', $columns).'_index');
    }

    private function cacheKey(): string
    {
        $connection = $this->connection();

        return implode(':', [
            'visitor-counter:schema:v'.self::VERSION,
            $connection ?? DB::getDefaultConnection(),
            DB::connection($connection)->getDatabaseName(),
            $this->table(),
        ]);
    }

    private function isMarked(): bool
    {
        try {
            return Cache::get($this->cacheKey()) === true;
        } catch (Throwable) {
            return false;
        }
    }

    private function mark(): void
    {
        $this->ensured = true;

        try {
            Cache::forever($this->cacheKey(), true);
        } catch (Throwable) {
            // Cache tidak tersedia: request berikutnya memeriksa skema lagi (aman, hanya lebih lambat).
        }
    }
}
