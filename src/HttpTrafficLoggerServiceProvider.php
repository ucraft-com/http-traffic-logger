<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use Illuminate\Support\ServiceProvider;

use function config_path;

/**
 * Service provider of the package.
 *
 * @author Tigran Mesropyan <tiko@ucraft.com>
 */
class HttpTrafficLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/config.php',
            'http-traffic-logger'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__.'/../config/config.php' => config_path('http-traffic-logger.php'),
                ],
                'http-traffic-logger'
            );
        }
    }
}
