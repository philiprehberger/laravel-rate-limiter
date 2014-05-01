<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Algorithms;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PhilipRehberger\RateLimiter\Contracts\RateLimitAlgorithm;
use PhilipRehberger\RateLimiter\RateLimitResult;

/**
 * Token Bucket Rate Limiting Algorithm.
 *
 * Maintains a bucket that is continuously refilled with tokens at a fixed rate
 * (limit / window tokens per second). Each request consumes tokens. When the
 * bucket is empty the request is denied. This algorithm naturally smooths
 * traffic and allows short, controlled bursts up to the bucket capacity.
 *
 * Bucket state is stored as a serialised array: ['tokens' => float, 'last' => float].
 */
class TokenBucketAlgorithm implements RateLimitAlgorithm
{
    public function attempt(string $key, int $limit, int $window, int $cost): RateLimitResult
    {
        $store = $this->store();
        $now = microtime(true);

        /** @var array{tokens: float, last: float}|null $bucket */
        $bucket = $store->get($key);

        if ($bucket === null) {
            $bucket = ['tokens' => (float) $limit, 'last' => $now];
        }

        // Refill tokens based on elapsed time
        $refillRate = $limit / $window; // tokens per second
        $elapsed = $now - $bucket['last'];
        $bucket['tokens'] = min((float) $limit, $bucket['tokens'] + ($elapsed * $refillRate));
        $bucket['last'] = $now;

        if ($bucket['tokens'] < $cost) {
            // Calculate when enough tokens will be available
            $tokensNeeded = $cost - $bucket['tokens'];
            $retryAfter = (int) ceil($tokensNeeded / $refillRate);
            $resetAt = (int) ceil($now + ($limit - $bucket['tokens']) / $refillRate);

            $store->put($key, $bucket, $window * 2);

            return new RateLimitResult(
                allowed: false,
                remaining: (int) floor($bucket['tokens']),
                limit: $limit,
                retryAfter: max(1, $retryAfter),
                resetAt: $resetAt,
            );
        }

        $bucket['tokens'] -= $cost;
        $store->put($key, $bucket, $window * 2);

        $remaining = (int) floor($bucket['tokens']);
        $resetAt = $remaining < $limit
            ? (int) ceil($now + ($limit - $bucket['tokens']) / $refillRate)
            : (int) $now;

        return new RateLimitResult(
            allowed: true,
            remaining: $remaining,
            limit: $limit,
            retryAfter: null,
            resetAt: $resetAt,
        );
    }

    public function check(string $key, int $limit, int $window): RateLimitResult
    {
        $store = $this->store();
        $now = microtime(true);

        /** @var array{tokens: float, last: float}|null $bucket */
        $bucket = $store->get($key);

        if ($bucket === null) {
            return new RateLimitResult(
                allowed: true,
                remaining: $limit,
                limit: $limit,
                retryAfter: null,
                resetAt: (int) $now,
            );
        }

        $refillRate = $limit / $window;
        $elapsed = $now - $bucket['last'];
        $currentTokens = min((float) $limit, $bucket['tokens'] + ($elapsed * $refillRate));
        $remaining = (int) floor($currentTokens);
        $allowed = $currentTokens >= 1;
        $resetAt = (int) ceil($now + ($limit - $currentTokens) / $refillRate);

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining,
            limit: $limit,
            retryAfter: $allowed ? null : (int) ceil((1 - $currentTokens) / $refillRate),
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
