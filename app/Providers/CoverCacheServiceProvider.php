<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Illuminate\Support\ServiceProvider;

class CoverCacheServiceProvider extends ServiceProvider
{
	/**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CoverCache::class, function ($app) {
            return new CoverCache();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [CoverCache::class];
    }
}
