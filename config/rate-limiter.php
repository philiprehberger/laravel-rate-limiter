<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used when no algorithm is explicitly specified on a
    | PendingRateLimit builder. Supported: "fixed", "sliding", "token_bucket"
    |
    | - fixed        Simple counter per window. Fast, may allow bursts at edges.
    | - sliding      Rolling timestamp log. Most accurate, slightly more storage.
    | - token_bucket Continuous refill bucket. Smooths traffic, allows bursts.
    |
    */

    'default_algorithm' => env('RATE_LIMITER_ALGORITHM', 'sliding'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache driver to use for storing rate limit counters. When set to
    | null the application's default cache store is used. For high-traffic
    | production environments Redis or Memcached is strongly recommended.
    |
    | Example: 'redis', 'memcached', 'array', null
    |
    */

    'cache_store' => env('RATE_LIMITER_CACHE_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | All cache keys written by this package are prefixed with this string
    | to avoid collisions with other application cache data.
    |
    */

    'prefix' => env('RATE_LIMITER_PREFIX', 'rate_limit'),

];
