<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Algorithms;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PhilipRehberger\RateLimiter\Contracts\RateLimitAlgorithm;
use PhilipRehberger\RateLimiter\RateLimitResult;

/**
 * Sliding Window Rate Limiting Algorithm.
 *
 * Tracks individual request timestamps within the rolling window. Prunes
 * entries older than the window on each operation. More accurate than a fixed
 * window because it prevents burst exploitation at window boundaries, at the
 * cost of slightly more cache storage per key.
 */
class SlidingWindowAlgorithm implements RateLimitAlgorithm
{
    public function attempt(string $key, int $limit, int $window, int $cost): RateLimitResult
    {
        $store = $this->store();
        $now = (int) (microtime(true) * 1000); // millisecond precision
        $windowMs = $window * 1000;
        $cutoff = $now - $windowMs;

        /** @var list<int> $timestamps */
        $timestamps = $store->get($key, []);

        // Prune timestamps outside the current window
        $timestamps = array_values(array_filter($timestamps, fn (int $ts): bool => $ts > $cutoff));

        $current = count($timestamps);

        if ($current + $cost > $limit) {
            $oldest = $timestamps[0] ?? $now;
            $retryAfter = (int) ceil(($oldest + $windowMs - $now) / 1000);

            return new RateLimitResult(
                allowed: false,
                remaining: max(0, $limit - $current),
                limit: $limit,
                retryAfter: max(1, $retryAfter),
                resetAt: (int) ceil(($oldest + $windowMs) / 1000),
            );
        }

        // Add cost-many timestamps for this request
        for ($i = 0; $i < $cost; $i++) {
            $timestamps[] = $now;
        }

        $store->put($key, $timestamps, $window + 1);

        $oldest = $timestamps[0] ?? $now;
        $resetAt = (int) ceil(($oldest + $windowMs) / 1000);

        return new RateLimitResult(
            allowed: true,
            remaining: max(0, $limit - ($current + $cost)),
            limit: $limit,
            retryAfter: null,
            resetAt: $resetAt,
        );
    }

    public function check(string $key, int $limit, int $window): RateLimitResult
    {
        $store = $this->store();
        $now = (int) (microtime(true) * 1000);
        $windowMs = $window * 1000;
        $cutoff = $now - $windowMs;

        /** @var list<int> $timestamps */
        $timestamps = $store->get($key, []);
        $timestamps = array_values(array_filter($timestamps, fn (int $ts): bool => $ts > $cutoff));

        $current = count($timestamps);
        $remaining = max(0, $limit - $current);
        $allowed = $current < $limit;
        $oldest = $timestamps[0] ?? $now;
        $resetAt = (int) ceil(($oldest + $windowMs) / 1000);

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining,
            limit: $limit,
            retryAfter: $allowed ? null : max(1, (int) ceil(($oldest + $windowMs - $now) / 1000)),
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
