<?php

namespace Colligator\Providers;

use Colligator\CoverCache;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
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
        $this->app->singleton(CoverCache::class, function () {
            $fs = \Storage::disk('s3')->getAdapter();
            $im = $this->app->make(ImageManager::class);
            $conf = new FlysystemConfig([
                // Default: 30 days
                'CacheControl' => 'max-age=' . env('IMAGE_CACHE_TIME', 3153600) . ', public',
            ]);
            $http = $this->app->make(HttpClient::class);
            $messageFactory = $this->app->make(MessageFactory::class);
            return new CoverCache($fs, $im, $conf, $http, $messageFactory);
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
