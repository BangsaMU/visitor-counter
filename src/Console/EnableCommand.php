<?php

namespace Bangsamu\VisitorCounter\Console;

use Bangsamu\VisitorCounter\Events\RecordingToggled;
use Bangsamu\VisitorCounter\Support\RecordingSwitch;
use Illuminate\Console\Command;

final class EnableCommand extends Command
{
    protected $signature = 'visitor-counter:enable';

    protected $description = 'Nyalakan lagi pencatatan visitor';

    public function handle(RecordingSwitch $switch): int
    {
        $switch->enable();
        event(new RecordingToggled(true, null, RecordingToggled::SOURCE_CONSOLE));

        if (! $switch->configEnabled()) {
            $this->components->warn('Saklar dinyalakan, tetapi config visitor-counter.enabled (VISITOR_COUNTER_ENABLED) masih false.');

            return self::SUCCESS;
        }

        $this->components->info('Pencatatan visitor dinyalakan.');

        return self::SUCCESS;
    }
}
