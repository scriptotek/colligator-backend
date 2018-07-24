<?php

namespace Colligator\Facades;

use Illuminate\Support\Facades\Facade;

class CoverCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Colligator\CoverCache::class;
    }
}
