<?php

namespace Sarfraznawaz2005\Indexer;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
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

        Route::group([
            'namespace' => 'Sarfraznawaz2005\Indexer\Http\Controllers',
            'prefix' => 'indexer'
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        });

        $this->registerMiddleware(IndexerMiddleware::class);
    }

    public function register()
    {
        $this->app->singleton(Indexer::class);

        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'indexer');
    }

    /**
     * Register the middleware
     *
     * @param string $middleware
     */
    protected function registerMiddleware($middleware)
    {
        $kernel = $this->app[Kernel::class];
        $kernel->pushMiddleware($middleware);
    }
}
