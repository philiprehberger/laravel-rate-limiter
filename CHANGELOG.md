# Changelog

All notable changes to `philiprehberger/laravel-rate-limiter` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.1.0] - 2026-03-13

### Added
- Input validation for `allow()` (must be >= 1) and `per()` (must be >= 1) — throws `InvalidArgumentException`

### Fixed
- Potential division by zero in `TokenBucketAlgorithm` when window is zero

### Removed
- Unreachable `default` case in algorithm resolution

### Changed
- Custom algorithm documentation clarified as not yet registrable by name in the fluent API

---

## [1.0.0] - 2026-03-09

### Added
- `RateLimit` entry-point class with `for()`, `forUser()`, and `forIp()` static factory methods
- `PendingRateLimit` fluent builder: `allow()`, `per()`, `perSecond()`, `perMinute()`, `perHour()`, `perDay()`, `algorithm()`, `cost()`, `on()`, `attempt()`, `check()`
- `RateLimitResult` value object with `allowed()`, `denied()`, and `headers()` methods
- Three rate limiting algorithms:
  - `FixedWindowAlgorithm` — simple counter per window with TTL
  - `SlidingWindowAlgorithm` — rolling timestamp log with millisecond precision
  - `TokenBucketAlgorithm` — continuously refilling token bucket
- `RateLimitAlgorithm` contract (interface) for custom algorithm implementations
- `RateLimitMiddleware` — route middleware via `rate-limit:max,window,algorithm`
- `RateLimiterServiceProvider` — auto-discovery, config publishing, middleware alias
- `RateLimit` facade
- Configurable cache store, key prefix, and default algorithm via `config/rate-limiter.php`
- Full PHPUnit 11 test suite (Orchestra Testbench)
- PHPStan level-8 static analysis configuration
- Laravel Pint code style configuration
- GitHub Actions CI matrix (PHP 8.2 / 8.3 / 8.4, Laravel 11 / 12)
