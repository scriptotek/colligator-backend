<?php

namespace Colligator\Providers;

use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

class HttpClientProvider extends ServiceProvider
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
        // PSR-18 compatible HTTP client
        $this->app->singleton(ClientInterface::class, function () {
            return new \AlexTartan\GuzzlePsr18Adapter\Client([
                'timeout' => 30,
                'verify' => false,  // we need to fetch covers from at least one server with invalid ssl setup
            ]);
        });

        // PSR-17 compatible request factory
        $this->app->singleton(RequestFactoryInterface::class, function () {
            return new RequestFactory();
        });

        // PSR-17 compatible URI factory
        $this->app->singleton(UriFactoryInterface::class, function () {
            return new UriFactory();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ClientInterface::class,
            RequestFactoryInterface::class,
            UriFactoryInterface::class,
        ];
    }
}
