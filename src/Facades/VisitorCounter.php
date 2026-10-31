<?php

namespace Bangsamu\VisitorCounter\Facades;

use Bangsamu\VisitorCounter\VisitorStats;
use Illuminate\Support\Facades\Facade;

/**
 * @method static int total()
 * @method static int today()
 * @method static int monthly()
 * @method static int unique()
 * @method static int uniqueToday()
 * @method static \Illuminate\Support\Collection<int, array{date: string, hits: int, unique_ips: int, unique_users: int}> daily(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to)
 *
 * @see VisitorStats
 */
class VisitorCounter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'visitor-counter';
    }
}
