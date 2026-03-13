<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PhilipRehberger\RateLimiter\Middleware\RateLimitMiddleware;

class RateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__.'/../config/rate-limiter.php' => config_path('rate-limiter.php'),
                ],
                'rate-limiter-config',
            );
        }

        // Register middleware alias for Laravel 10 and below.
        // Laravel 11+ users should register via bootstrap/app.php.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('rate-limit', RateLimitMiddleware::class);
    }

    /**
     * Register package bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rate-limiter.php',
            'rate-limiter',
        );

        // Bind the concrete RateLimit class so the facade resolves correctly
        $this->app->bind(RateLimit::class, RateLimit::class);
    }
}
