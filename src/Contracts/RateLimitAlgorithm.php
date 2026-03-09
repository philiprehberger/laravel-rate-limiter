<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Contracts;

use PhilipRehberger\RateLimiter\RateLimitResult;

interface RateLimitAlgorithm
{
    /**
     * Attempt to consume tokens from the rate limit bucket.
     *
     * @param  string  $key  The cache key for this rate limit
     * @param  int  $limit  Maximum number of attempts allowed in the window
     * @param  int  $window  Window size in seconds
     * @param  int  $cost  Number of tokens to consume
     */
    public function attempt(string $key, int $limit, int $window, int $cost): RateLimitResult;

    /**
     * Check the current rate limit state without consuming any tokens.
     *
     * @param  string  $key  The cache key for this rate limit
     * @param  int  $limit  Maximum number of attempts allowed in the window
     * @param  int  $window  Window size in seconds
     */
    public function check(string $key, int $limit, int $window): RateLimitResult;
}
