<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PhilipRehberger\RateLimiter\RateLimiterServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RateLimiterServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use the array cache driver so all tests run in-memory without
        // Redis or Memcached. The algorithms resolve the store through
        // app('cache.store'), which points to this driver.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('rate-limiter.default_algorithm', 'sliding');
        $app['config']->set('rate-limiter.cache_store', null);
        $app['config']->set('rate-limiter.prefix', 'rate_limit');
    }
}
