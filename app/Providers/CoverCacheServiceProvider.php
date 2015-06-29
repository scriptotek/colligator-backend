<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Illuminate\Support\ServiceProvider;

class CoverCacheServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        \App::bind('covercache', function()
        {
            return new CoverCache;
        });
    }
}
