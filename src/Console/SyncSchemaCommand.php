<?php

namespace Bangsamu\VisitorCounter\Console;

use Bangsamu\VisitorCounter\Support\VisitorSchema;
use Illuminate\Console\Command;

final class SyncSchemaCommand extends Command
{
    protected $signature = 'visitor-counter:sync-schema';

    protected $description = 'Buat tabel visitor bila belum ada, atau tambahkan kolom/index yang kurang';

    public function handle(VisitorSchema $schema): int
    {
        $changes = $schema->sync();

        if ($changes === []) {
            $this->components->info('Tabel '.$schema->table().' sudah lengkap.');

            return self::SUCCESS;
        }

        $this->components->info('Tabel '.$schema->table().' diperbarui:');
        $this->components->bulletList($changes);

        return self::SUCCESS;
    }
}
