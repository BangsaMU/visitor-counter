<?php

use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * v1.0: tambah kolom pelacakan (method, route, status, durasi, sesi, IP klien) ke tabel yang dibuat v0.0.x. Kolom
 * dan index yang sudah ada dilewati.
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
        // Sengaja kosong: kolom berisi data kunjungan, rollback paket tidak menghapusnya.
    }
};
