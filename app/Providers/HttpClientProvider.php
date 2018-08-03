<?php

namespace Colligator\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\MessageFactory\GuzzleMessageFactory;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

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
        $this->app->singleton(HttpClient::class, function () {
            $config = [
                'timeout' => 30,
                'verify' => false,  // we need to fetch covers from at least one server with invalid ssl setup
            ];
            $client = new GuzzleClient($config);
            $adapter = new GuzzleAdapter($client);

            return $adapter;
        });

        $this->app->singleton(MessageFactory::class, function () {
            $factory = new GuzzleMessageFactory();

            return $factory;
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
            HttpClient::class,
            MessageFactory::class,
        ];
    }
}
