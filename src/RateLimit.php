<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Entry point for building rate limit configurations.
 *
 * This class is the concrete implementation that backs the RateLimit facade.
 * All methods are static factory helpers that return a PendingRateLimit
 * for fluent chaining.
 *
 * Usage:
 *   RateLimit::for('global')->allow(1000)->perHour()->attempt();
 *   RateLimit::forUser($user)->allow(60)->perMinute()->attempt();
 *   RateLimit::forIp($request->ip())->allow(10)->perMinute()->algorithm('fixed')->attempt();
 */
class RateLimit
{
    /**
     * Start a rate limit for an arbitrary string key.
     *
     * @param  string  $key  A unique identifier for the rate limit target
     */
    public static function for(string $key): PendingRateLimit
    {
        return new PendingRateLimit($key);
    }

    /**
     * Start a rate limit scoped to an authenticated user.
     *
     * The cache key will be prefixed with 'user:{id}'.
     */
    public static function forUser(Authenticatable $user): PendingRateLimit
    {
        return new PendingRateLimit('user:'.$user->getAuthIdentifier());
    }

    /**
     * Start a rate limit scoped to an IP address.
     *
     * The cache key will be prefixed with 'ip:{address}'.
     */
    public static function forIp(string $ip): PendingRateLimit
    {
        return new PendingRateLimit('ip:'.$ip);
    }
}
