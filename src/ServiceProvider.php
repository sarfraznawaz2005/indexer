<?php

namespace Sarfraznawaz2005\Indexer;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/config.php' => config_path('indexer.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->app->make(Indexer::class);

        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'indexer');
    }
}
