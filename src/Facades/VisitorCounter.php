<?php

namespace Bangsamu\VisitorCounter\Facades;

use Illuminate\Support\Facades\Facade;

class VisitorCounter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'visitor-counter';
    }
}
