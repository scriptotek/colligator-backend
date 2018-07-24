<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use League\Flysystem\Config as FlysystemConfig;

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
            $fs = \Storage::disk('s3')->getAdapter();
            $im = $this->app->make(ImageManager::class);
            $conf = new FlysystemConfig([
                // Default: 30 days
                'CacheControl' => 'max-age=' . env('IMAGE_CACHE_TIME', 3153600) . ', public',
            ]);
            return new CoverCache($fs, $im, $conf);
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
