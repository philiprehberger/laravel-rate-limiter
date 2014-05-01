<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter;

use InvalidArgumentException;
use PhilipRehberger\RateLimiter\Algorithms\FixedWindowAlgorithm;
use PhilipRehberger\RateLimiter\Algorithms\SlidingWindowAlgorithm;
use PhilipRehberger\RateLimiter\Algorithms\TokenBucketAlgorithm;
use PhilipRehberger\RateLimiter\Contracts\RateLimitAlgorithm;

/**
 * Fluent builder for a single rate limit configuration.
 *
 * Example:
 *   RateLimit::forUser($user)->allow(100)->perMinute()->attempt();
 */
class PendingRateLimit
{
    private int $maxAttempts = 60;

    private int $windowSeconds = 60;

    private string $algorithmName = 'sliding';

    private int $costPerOperation = 1;

    private string $baseKey;

    public function __construct(string $key)
    {
        $prefix = config('rate-limiter.prefix', 'rate_limit');
        $this->baseKey = $prefix.':'.$key;
        $this->algorithmName = config('rate-limiter.default_algorithm', 'sliding');
    }

    // -------------------------------------------------------------------------
    // Limit configuration
    // -------------------------------------------------------------------------

    /**
     * Set the maximum number of attempts allowed within the window.
     */
    public function allow(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Window helpers
    // -------------------------------------------------------------------------

    /**
     * Set window to N seconds.
     */
    public function per(int $seconds): self
    {
        $this->windowSeconds = $seconds;

        return $this;
    }

    /**
     * Set window to 1 second.
     */
    public function perSecond(): self
    {
        return $this->per(1);
    }

    /**
     * Set window to 60 seconds.
     */
    public function perMinute(): self
    {
        return $this->per(60);
    }

    /**
     * Set window to 3600 seconds.
     */
    public function perHour(): self
    {
        return $this->per(3600);
    }

    /**
     * Set window to 86400 seconds.
     */
    public function perDay(): self
    {
        return $this->per(86400);
    }

    // -------------------------------------------------------------------------
    // Algorithm & cost
    // -------------------------------------------------------------------------

    /**
     * Choose the rate limiting algorithm.
     *
     * Accepted values: 'fixed', 'sliding', 'token_bucket'
     */
    public function algorithm(string $algo): self
    {
        $valid = ['fixed', 'sliding', 'token_bucket'];

        if (! in_array($algo, $valid, true)) {
            throw new InvalidArgumentException(
                "Unknown rate limit algorithm '{$algo}'. Valid options: ".implode(', ', $valid),
            );
        }

        $this->algorithmName = $algo;

        return $this;
    }

    /**
     * Set the token cost for each operation.
     *
     * Useful for weighting expensive operations higher than cheap ones.
     */
    public function cost(int $cost = 1): self
    {
        $this->costPerOperation = max(1, $cost);

        return $this;
    }

    /**
     * Append an action suffix to create a composite cache key.
     *
     * Allows reusing the same entity key across different actions:
     *   RateLimit::forUser($user)->on('login')->allow(5)->perMinute()->attempt()
     *   RateLimit::forUser($user)->on('export')->allow(10)->perHour()->attempt()
     */
    public function on(string $action): self
    {
        $this->baseKey .= ':'.$action;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Terminal methods
    // -------------------------------------------------------------------------

    /**
     * Try to consume the configured cost from the rate limit bucket.
     *
     * Returns a RateLimitResult indicating whether the operation is allowed
     * and the current state of the limit.
     */
    public function attempt(): RateLimitResult
    {
        return $this->resolveAlgorithm()->attempt(
            $this->baseKey,
            $this->maxAttempts,
            $this->windowSeconds,
            $this->costPerOperation,
        );
    }

    /**
     * Check the current rate limit state without consuming any tokens.
     *
     * Safe to call frequently; does not affect the counter.
     */
    public function check(): RateLimitResult
    {
        return $this->resolveAlgorithm()->check(
            $this->baseKey,
            $this->maxAttempts,
            $this->windowSeconds,
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function resolveAlgorithm(): RateLimitAlgorithm
    {
        return match ($this->algorithmName) {
            'fixed' => new FixedWindowAlgorithm,
            'sliding' => new SlidingWindowAlgorithm,
            'token_bucket' => new TokenBucketAlgorithm,
            default => new SlidingWindowAlgorithm,
        };
    }
}
