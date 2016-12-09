<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Illuminate\Support\ServiceProvider;

class CoverCacheServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        \App::bind('covercache', function () {
            return new CoverCache();
        });
    }
}
