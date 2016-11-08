# laravel-rate-limiter

[![Tests](https://github.com/philiprehberger/laravel-rate-limiter/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-rate-limiter/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/philiprehberger/laravel-rate-limiter.svg)](https://packagist.org/packages/philiprehberger/laravel-rate-limiter)
[![PHP Version](https://img.shields.io/packagist/php-v/philiprehberger/laravel-rate-limiter.svg)](https://packagist.org/packages/philiprehberger/laravel-rate-limiter)
[![License](https://img.shields.io/packagist/l/philiprehberger/laravel-rate-limiter.svg)](LICENSE)

Advanced rate limiting for Laravel 11 and 12. Choose from three algorithms — **fixed window**, **sliding window**, or **token bucket** — with a fluent API for per-user, per-IP, and per-action controls.

---

## Installation

```bash
composer require philiprehberger/laravel-rate-limiter
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=rate-limiter-config
```

---

## Quick Start

```php
use PhilipRehberger\RateLimiter\Facades\RateLimit;

// Rate limit by arbitrary key
$result = RateLimit::for('global-api')
    ->allow(1000)
    ->perHour()
    ->attempt();

if ($result->denied()) {
    return response()->json(['message' => 'Slow down'], 429, $result->headers());
}
```

---

## Fluent API

### Entry Points

```php
// Arbitrary key
RateLimit::for('some-key');

// Scoped to an authenticated user
RateLimit::forUser($request->user());

// Scoped to an IP address
RateLimit::forIp($request->ip());
```

### Configuring the Limit

```php
RateLimit::for('key')
    ->allow(100)         // max attempts per window (must be >= 1)
    ->perMinute()        // window = 60 seconds (per() requires >= 1)
    ->algorithm('sliding')
    ->cost(1)            // tokens to consume per attempt
    ->on('export');      // composite key: "key:export"
```

**Window helpers:**

| Method | Window |
|--------|--------|
| `perSecond()` | 1 second |
| `perMinute()` | 60 seconds |
| `perHour()` | 3 600 seconds |
| `perDay()` | 86 400 seconds |
| `per(int $seconds)` | custom |

### Terminal Methods

```php
// Consume tokens and return the result
$result = $pending->attempt();

// Inspect state without consuming tokens
$result = $pending->check();
```

### RateLimitResult

```php
$result->allowed();    // bool
$result->denied();     // bool
$result->remaining;    // int
$result->limit;        // int
$result->retryAfter;   // int|null — seconds until allowed (null if allowed)
$result->resetAt;      // int — Unix timestamp of next window reset
$result->headers();    // array — standard HTTP rate limit headers
```

**headers() output:**

```php
[
    'X-RateLimit-Limit'     => 100,
    'X-RateLimit-Remaining' => 42,
    'X-RateLimit-Reset'     => 1712345678,
    // only present when denied:
    'Retry-After'           => 30,
]
```

---

## Algorithms

### Fixed Window

```php
->algorithm('fixed')
```

Counts requests in a fixed-length window that starts when the first request arrives. Fast and memory-efficient. The trade-off: a burst of requests at the end of one window and the start of the next can briefly allow up to `2 * limit` requests.

**Best for:** internal tooling, admin APIs, scenarios where occasional boundary bursts are acceptable.

---

### Sliding Window

```php
->algorithm('sliding')   // default
```

Stores a millisecond-precision timestamp for every request and prunes entries outside the rolling window on each operation. Prevents boundary bursts entirely. Uses slightly more cache memory than the fixed window because individual timestamps are stored rather than a single counter.

**Best for:** public APIs, authentication endpoints, any scenario requiring strict burst prevention.

---

### Token Bucket

```php
->algorithm('token_bucket')
```

Maintains a bucket that refills continuously at a rate of `limit / window` tokens per second. Requests consume tokens; an empty bucket rejects the request. Allows controlled short bursts (up to bucket capacity) while smoothing sustained traffic.

**Best for:** upload/download throttling, expensive compute endpoints, scenarios where some bursty traffic is acceptable but sustained overload must be prevented.

---

## Per-Entity and Composite Keys

```php
// Per authenticated user
RateLimit::forUser(auth()->user())
    ->allow(60)
    ->perMinute()
    ->attempt();

// Per IP
RateLimit::forIp($request->ip())
    ->allow(20)
    ->perMinute()
    ->attempt();

// Composite key: same user, different action budgets
RateLimit::forUser($user)->on('login')->allow(5)->perMinute()->attempt();
RateLimit::forUser($user)->on('export')->allow(10)->perHour()->attempt();
```

---

## Cost-Based Limiting

Weight expensive operations higher than cheap ones:

```php
// A bulk export costs 10 tokens against a 100-token-per-hour budget
RateLimit::forUser($user)
    ->on('bulk-export')
    ->allow(100)
    ->perHour()
    ->cost(10)
    ->attempt();
```

---

## Middleware

Register the alias in `bootstrap/app.php` (Laravel 11+):

```php
use PhilipRehberger\RateLimiter\Middleware\RateLimitMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['rate-limit' => RateLimitMiddleware::class]);
})
```

The package also registers the alias automatically for compatibility with older versions.

Apply to routes:

```php
// 100 requests per 60 seconds, sliding window
Route::middleware('rate-limit:100,60,sliding')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});

// 50 requests per hour, fixed window
Route::middleware('rate-limit:50,3600,fixed')->group(function () {
    Route::post('/api/export', [ExportController::class, 'create']);
});

// Uses default algorithm from config
Route::middleware('rate-limit:200,60')->group(function () {
    Route::apiResource('/api/products', ProductController::class);
});
```

Middleware parameters (all positional):

| Position | Parameter | Default |
|----------|-----------|---------|
| 1 | `maxAttempts` | 60 |
| 2 | `windowSeconds` | 60 |
| 3 | `algorithm` | config default |

When the limit is exceeded the middleware returns a `429 Too Many Requests` JSON response with `Retry-After` and `X-RateLimit-*` headers. Allowed responses receive `X-RateLimit-*` headers automatically.

---

## Configuration

`config/rate-limiter.php`:

```php
return [
    // 'fixed' | 'sliding' | 'token_bucket'
    'default_algorithm' => env('RATE_LIMITER_ALGORITHM', 'sliding'),

    // null = use the application's default cache store
    // For production, use 'redis' or 'memcached'
    'cache_store' => env('RATE_LIMITER_CACHE_STORE', null),

    // Prefix for all cache keys written by this package
    'prefix' => env('RATE_LIMITER_PREFIX', 'rate_limit'),
];
```

---

## Custom Algorithms

The `RateLimitAlgorithm` contract is public and can be implemented directly:

```php
use PhilipRehberger\RateLimiter\Contracts\RateLimitAlgorithm;
use PhilipRehberger\RateLimiter\RateLimitResult;

class MyAlgorithm implements RateLimitAlgorithm
{
    public function attempt(string $key, int $limit, int $window, int $cost): RateLimitResult
    {
        // ...
    }

    public function check(string $key, int $limit, int $window): RateLimitResult
    {
        // ...
    }
}
```

> **Note:** Custom algorithms cannot yet be registered by name in the fluent API. Use the built-in `'fixed'`, `'sliding'`, or `'token_bucket'` algorithms, or instantiate your algorithm directly.

---

## Testing

```bash
composer test
```

---

## License

MIT — see [LICENSE](LICENSE).
