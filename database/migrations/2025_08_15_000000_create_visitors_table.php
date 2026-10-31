<?php

use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Buat tabel visitor bila belum ada, atau lengkapi kolom/index tabel yang sudah ada (buatan app atau v0.0.x), jadi
 * tidak gagal "Table already exists".
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return app(VisitorSchema::class)->connection();
    }

    public function up(): void
    {
        app(VisitorSchema::class)->sync();
    }

    public function down(): void
    {
        // Sengaja kosong: tabel bisa milik app dan berisi log kunjungan, rollback paket tidak menghapusnya.
    }
};
