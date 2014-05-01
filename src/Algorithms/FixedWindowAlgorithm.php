<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Algorithms;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PhilipRehberger\RateLimiter\Contracts\RateLimitAlgorithm;
use PhilipRehberger\RateLimiter\RateLimitResult;

/**
 * Fixed Window Rate Limiting Algorithm.
 *
 * Counts requests within a fixed time window. The window resets at a fixed
 * boundary (e.g. every 60 seconds from when the first request arrived).
 * Simple and efficient but can allow bursts at window boundaries.
 */
class FixedWindowAlgorithm implements RateLimitAlgorithm
{
    public function attempt(string $key, int $limit, int $window, int $cost): RateLimitResult
    {
        $store = $this->store();
        $countKey = $key.':count';
        $resetKey = $key.':reset';

        $now = time();

        // Get or initialise the window reset timestamp
        $resetAt = $store->get($resetKey);

        if ($resetAt === null || $now >= $resetAt) {
            // Start a fresh window
            $resetAt = $now + $window;
            $store->put($resetKey, $resetAt, $window + 1);
            $store->put($countKey, 0, $window + 1);
        }

        $current = (int) $store->get($countKey, 0);

        if ($current + $cost > $limit) {
            return new RateLimitResult(
                allowed: false,
                remaining: max(0, $limit - $current),
                limit: $limit,
                retryAfter: max(1, $resetAt - $now),
                resetAt: $resetAt,
            );
        }

        $store->increment($countKey, $cost);
        $newCount = $current + $cost;

        return new RateLimitResult(
            allowed: true,
            remaining: max(0, $limit - $newCount),
            limit: $limit,
            retryAfter: null,
            resetAt: $resetAt,
        );
    }

    public function check(string $key, int $limit, int $window): RateLimitResult
    {
        $store = $this->store();
        $countKey = $key.':count';
        $resetKey = $key.':reset';

        $now = time();
        $resetAt = $store->get($resetKey) ?? ($now + $window);
        $current = (int) $store->get($countKey, 0);
        $remaining = max(0, $limit - $current);
        $allowed = $current < $limit;

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining,
            limit: $limit,
            retryAfter: $allowed ? null : max(1, $resetAt - $now),
            resetAt: $resetAt,
        );
    }

    private function store(): Repository
    {
        $driver = config('rate-limiter.cache_store') ?: null;

        return $driver !== null
            ? Cache::store($driver)
            : app('cache.store');
    }
}
