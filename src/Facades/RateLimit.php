<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the RateLimit entry-point class.
 *
 * @method static \PhilipRehberger\RateLimiter\PendingRateLimit for(string $key)
 * @method static \PhilipRehberger\RateLimiter\PendingRateLimit forUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \PhilipRehberger\RateLimiter\PendingRateLimit forIp(string $ip)
 *
 * @see \PhilipRehberger\RateLimiter\RateLimit
 */
class RateLimit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PhilipRehberger\RateLimiter\RateLimit::class;
    }
}
