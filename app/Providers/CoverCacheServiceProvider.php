<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use League\Flysystem\Config as FlysystemConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

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
        $this->app->singleton(CoverCache::class, function () {
            $fs = \Storage::disk('s3')->getAdapter();
            $im = $this->app->make(ImageManager::class);
            $conf = new FlysystemConfig([
                // Default: 30 days
                'CacheControl' => 'max-age=' . env('IMAGE_CACHE_TIME', 3153600) . ', public',
            ]);
            $http = $this->app->make(ClientInterface::class);
            $requestFactory = $this->app->make(RequestFactoryInterface::class);
            return new CoverCache($fs, $im, $conf, $http, $requestFactory);
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
