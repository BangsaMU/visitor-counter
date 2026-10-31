<?php

namespace Bangsamu\VisitorCounter\Console;

use Bangsamu\VisitorCounter\Events\RecordingToggled;
use Bangsamu\VisitorCounter\Support\RecordingSwitch;
use Illuminate\Console\Command;

final class DisableCommand extends Command
{
    protected $signature = 'visitor-counter:disable';

    protected $description = 'Matikan pencatatan visitor (tidak ada request yang disimpan sampai dinyalakan lagi)';

    public function handle(RecordingSwitch $switch): int
    {
        $switch->disable('artisan');
        event(new RecordingToggled(false, null, RecordingToggled::SOURCE_CONSOLE));

        $this->components->info('Pencatatan visitor dimatikan.');

        return self::SUCCESS;
    }
}
