<?php

namespace Bangsamu\VisitorCounter\Support;

use Bangsamu\VisitorCounter\Contracts\HolidayCalendar;
use Carbon\CarbonInterface;

final class NoHolidays implements HolidayCalendar
{
    public function isHoliday(CarbonInterface $date): bool
    {
        return false;
    }
}
