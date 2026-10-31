<?php

namespace Bangsamu\VisitorCounter\Contracts;

use Carbon\CarbonInterface;

/**
 * Hari libur untuk jadwal "di luar jam kerja". Bawaan paket: tidak ada hari libur. App mengikat implementasinya sendiri
 * (mis. dari tabel kalender), sebaiknya di-cache karena bisa dipanggil di setiap request jam kerja.
 */
interface HolidayCalendar
{
    public function isHoliday(CarbonInterface $date): bool;
}
