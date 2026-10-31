<?php

namespace Bangsamu\VisitorCounter\Support;

use Bangsamu\VisitorCounter\Contracts\HolidayCalendar;
use Carbon\CarbonInterface;

/**
 * Apakah request dicatat menurut jadwal pengaturan: mode user login / tamu (selalu, hanya di luar jam kerja, tidak).
 * Jam kerja = hari kerja, antara jam mulai dan selesai, dan bukan hari libur (bila hari libur diperhitungkan).
 */
final class RecordingSchedule
{
    public function __construct(
        private readonly VisitorSettings $settings,
        private readonly HolidayCalendar $holidays,
    ) {}

    public function allows(bool $authenticated, ?CarbonInterface $at = null): bool
    {
        $settings = $this->settings->all();

        return match ($authenticated ? $settings['users'] : $settings['guests']) {
            VisitorSettings::NEVER => false,
            VisitorSettings::OUTSIDE_WORK_HOURS => ! $this->isWorkTime($at ?? now()),
            default => true,
        };
    }

    public function isWorkTime(CarbonInterface $at): bool
    {
        $settings = $this->settings->all();
        $time = $at->format('H:i');

        if (! in_array($at->dayOfWeekIso, $settings['work_days'], true)
            || $time < $settings['work_start']
            || $time >= $settings['work_end']) {
            return false;
        }

        return ! ($settings['holidays'] && $this->holidays->isHoliday($at));
    }
}
