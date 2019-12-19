<?php

namespace Sarfraznawaz2005\QueryWatch;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/config.php' => config_path('querywatch.php'),
            ], 'config');
        }

        $this->registerMiddleware(QueryWatchMiddleware::class);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->singleton(QueryWatch::class);

        $this->app->alias(QueryWatch::class, 'querywatch');

        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'querywatch');
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
