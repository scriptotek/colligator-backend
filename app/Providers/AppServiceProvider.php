<?php

namespace Colligator\Providers;

use Colligator\SearchEngine;
use Illuminate\Support\ServiceProvider;
use Colligator\Cover;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(SearchEngine $se)
    {

        Cover::created(function ($cover) use ($se) {
            if ($cover->url && !$cover->isCached()) {
                $cover->cache();
                $se->indexDocument($cover->document);
            }
        });

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
