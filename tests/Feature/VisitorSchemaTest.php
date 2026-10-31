<?php

namespace Bangsamu\VisitorCounter\Tests\Feature;

use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Bangsamu\VisitorCounter\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class VisitorSchemaTest extends TestCase
{
    public function test_package_migrations_create_the_full_table(): void
    {
        foreach (['visit_date', 'user_id', 'session_hash', 'method', 'path', 'query_string', 'route_name', 'status', 'duration_ms', 'is_ajax', 'ip', 'client_ip', 'client_ip_source', 'user_agent', 'referer', 'created_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('visitors', $column), "kolom {$column} tidak ada");
        }

        $this->assertTrue(Schema::hasIndex('visitors', ['user_id', 'created_at']));
        $this->assertSame([], app(VisitorSchema::class)->sync());
    }

    public function test_old_v0_table_is_upgraded_without_error_and_keeps_its_rows(): void
    {
        Schema::drop('visitors');
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->date('visit_date');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('path')->nullable();
            $table->string('referer')->nullable();
            $table->timestamps();
            $table->index(['visit_date', 'ip']);
        });
        DB::table('visitors')->insert(['ip' => '10.0.0.1', 'visit_date' => '2026-01-02', 'path' => 'lama', 'created_at' => now(), 'updated_at' => now()]);

        $schema = app(VisitorSchema::class);
        $changes = $schema->sync();

        $this->assertContains('add column client_ip', $changes);
        $this->assertNotContains('add index visit_date, ip', $changes);
        $this->assertSame([], $schema->sync());
        $this->assertSame('lama', DB::table('visitors')->value('path'));

        $this->get('/halaman')->assertOk();
        $this->assertSame(2, DB::table('visitors')->count());
    }

    public function test_new_table_and_upgraded_v0_table_end_up_with_identical_columns(): void
    {
        $created = $this->columnSignature();

        Schema::drop('visitors');
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->date('visit_date');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('path')->nullable();
            $table->string('referer')->nullable();
            $table->timestamps();
            $table->index(['visit_date', 'ip']);
        });
        app(VisitorSchema::class)->sync();

        $this->assertSame($created, $this->columnSignature());
    }

    public function test_table_dropped_after_being_marked_complete_is_recreated_on_next_request(): void
    {
        app(VisitorSchema::class)->ensure();
        Schema::drop('visitors');

        $this->get('/halaman')->assertOk();

        $this->assertSame('halaman', DB::table('visitors')->value('route_name'));
    }

    public function test_auto_schema_off_never_creates_the_table_at_runtime(): void
    {
        config(['visitor-counter.auto_schema' => false]);
        Schema::drop('visitors');

        $this->get('/halaman')->assertOk();

        $this->assertFalse(Schema::hasTable('visitors'));
    }

    public function test_sync_schema_command_reports_complete_table(): void
    {
        $this->artisan('visitor-counter:sync-schema')
            ->expectsOutputToContain('sudah lengkap')
            ->assertSuccessful();
    }

    /**
     * @return array<string, string>
     */
    private function columnSignature(): array
    {
        return collect(Schema::getColumns('visitors'))
            ->mapWithKeys(fn (array $column): array => [$column['name'] => $column['type'].'|'.($column['nullable'] ? 'null' : 'not null').'|'.$column['default']])
            ->sortKeys()
            ->all();
    }
}
