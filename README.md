# Laravel Rate Limiter

[![Tests](https://github.com/philiprehberger/laravel-rate-limiter/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-rate-limiter/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/laravel-rate-limiter.svg)](https://packagist.org/packages/philiprehberger/laravel-rate-limiter)
[![License](https://img.shields.io/github/license/philiprehberger/laravel-rate-limiter)](LICENSE)

Advanced rate limiting with sliding window, token bucket, and per-entity controls for Laravel.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require philiprehberger/laravel-rate-limiter
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=rate-limiter-config
```

## Usage

### Quick Start

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

### Handling Rate-Limited Responses

```php
$result = RateLimit::for('api')
    ->allow(100)
    ->perMinute()
    ->attempt();

if ($result->denied()) {
    $seconds = $result->retryAfter(); // e.g. 23

    return response()->json([
        'message' => "Too many requests. Retry in {$seconds} seconds.",
        'retry_after' => $seconds,
        'remaining' => $result->remainingTokens(),
    ], 429, $result->headers());
}
```

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

### Algorithms

| Algorithm | String | Best For |
|-----------|--------|----------|
| Fixed Window | `'fixed'` | Internal tooling, scenarios where boundary bursts are acceptable |
| Sliding Window | `'sliding'` (default) | Public APIs, auth endpoints, strict burst prevention |
| Token Bucket | `'token_bucket'` | Upload/download throttling, expensive compute endpoints |

### Middleware

Apply to routes via the `rate-limit` alias:

```php
// 100 requests per 60 seconds, sliding window
Route::middleware('rate-limit:100,60,sliding')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});
```

### Configuration

`config/rate-limiter.php`:

```php
return [
    'default_algorithm' => env('RATE_LIMITER_ALGORITHM', 'sliding'),
    'cache_store'       => env('RATE_LIMITER_CACHE_STORE', null),
    'prefix'            => env('RATE_LIMITER_PREFIX', 'rate_limit'),
];
```

## API

### RateLimit Facade / Entry Points

| Method | Description |
|--------|-------------|
| `RateLimit::for(string $key)` | Create a pending limit for an arbitrary key |
| `RateLimit::forUser(Authenticatable $user)` | Create a pending limit scoped to a user |
| `RateLimit::forIp(string $ip)` | Create a pending limit scoped to an IP address |

### PendingRateLimit (Fluent Builder)

| Method | Description |
|--------|-------------|
| `->allow(int $limit)` | Set the maximum attempts per window |
| `->perSecond()` | Set window to 1 second |
| `->perMinute()` | Set window to 60 seconds |
| `->perHour()` | Set window to 3,600 seconds |
| `->perDay()` | Set window to 86,400 seconds |
| `->per(int $seconds)` | Set a custom window in seconds |
| `->algorithm(string $algo)` | Set algorithm: `'fixed'`, `'sliding'`, or `'token_bucket'` |
| `->cost(int $cost)` | Set token cost per attempt (default: 1) |
| `->on(string $action)` | Append an action suffix to the key |
| `->attempt()` | Consume tokens and return a `RateLimitResult` |
| `->check()` | Inspect state without consuming tokens |

### RateLimitResult

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `->allowed()` | `bool` | Whether the attempt was allowed |
| `->denied()` | `bool` | Whether the attempt was denied |
| `->retryAfter()` | `?int` | Seconds until next token is available; `null` if not rate-limited |
| `->remainingTokens()` | `int` | Remaining tokens in the current window (clamped to 0) |
| `->remaining` | `int` | Remaining tokens in current window |
| `->limit` | `int` | Configured limit |
| `->retryAfter` | `int\|null` | Seconds until allowed; `null` if allowed |
| `->resetAt` | `int` | Unix timestamp of next window reset |
| `->headers()` | `array` | Standard HTTP rate limit headers |

### Middleware Parameters

| Position | Parameter | Default |
|----------|-----------|---------|
| 1 | `maxAttempts` | 60 |
| 2 | `windowSeconds` | 60 |
| 3 | `algorithm` | config default |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
