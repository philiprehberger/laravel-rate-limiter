<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use PhilipRehberger\RateLimiter\RateLimitResult;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class RateLimitResultTest extends TestCase
{
    public function test_allowed_returns_true_when_allowed(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 9,
            limit: 10,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $this->assertTrue($result->allowed());
        $this->assertFalse($result->denied());
    }

    public function test_denied_returns_true_when_not_allowed(): void
    {
        $result = new RateLimitResult(
            allowed: false,
            remaining: 0,
            limit: 10,
            retryAfter: 30,
            resetAt: time() + 30,
        );

        $this->assertFalse($result->allowed());
        $this->assertTrue($result->denied());
    }

    public function test_headers_contain_correct_limit(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 5,
            limit: 100,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $headers = $result->headers();
        $this->assertSame(100, $headers['X-RateLimit-Limit']);
    }

    public function test_headers_contain_correct_remaining(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 42,
            limit: 100,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $headers = $result->headers();
        $this->assertSame(42, $headers['X-RateLimit-Remaining']);
    }

    public function test_headers_contain_reset_timestamp(): void
    {
        $resetAt = time() + 120;
        $result = new RateLimitResult(
            allowed: true,
            remaining: 5,
            limit: 10,
            retryAfter: null,
            resetAt: $resetAt,
        );

        $headers = $result->headers();
        $this->assertSame($resetAt, $headers['X-RateLimit-Reset']);
    }

    public function test_headers_include_retry_after_when_denied(): void
    {
        $result = new RateLimitResult(
            allowed: false,
            remaining: 0,
            limit: 10,
            retryAfter: 45,
            resetAt: time() + 45,
        );

        $headers = $result->headers();
        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertSame(45, $headers['Retry-After']);
    }

    public function test_headers_exclude_retry_after_when_allowed(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 5,
            limit: 10,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $headers = $result->headers();
        $this->assertArrayNotHasKey('Retry-After', $headers);
    }

    public function test_remaining_is_clamped_to_zero_in_headers(): void
    {
        // Negative remaining should not leak into headers
        $result = new RateLimitResult(
            allowed: false,
            remaining: -1,
            limit: 5,
            retryAfter: 10,
            resetAt: time() + 10,
        );

        $headers = $result->headers();
        $this->assertSame(0, $headers['X-RateLimit-Remaining']);
    }

    public function test_retry_after_returns_null_when_allowed(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 5,
            limit: 10,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $this->assertNull($result->retryAfter());
    }

    public function test_retry_after_returns_seconds_when_denied(): void
    {
        $result = new RateLimitResult(
            allowed: false,
            remaining: 0,
            limit: 10,
            retryAfter: 30,
            resetAt: time() + 30,
        );

        $this->assertSame(30, $result->retryAfter());
    }

    public function test_retry_after_method_returns_null_even_with_retry_after_property_when_allowed(): void
    {
        // Edge case: retryAfter property could be non-null but allowed is true
        $result = new RateLimitResult(
            allowed: true,
            remaining: 1,
            limit: 10,
            retryAfter: 15,
            resetAt: time() + 15,
        );

        $this->assertNull($result->retryAfter());
    }

    public function test_remaining_tokens_returns_remaining_when_positive(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 7,
            limit: 10,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $this->assertSame(7, $result->remainingTokens());
    }

    public function test_remaining_tokens_returns_zero_when_exhausted(): void
    {
        $result = new RateLimitResult(
            allowed: false,
            remaining: 0,
            limit: 10,
            retryAfter: 30,
            resetAt: time() + 30,
        );

        $this->assertSame(0, $result->remainingTokens());
    }

    public function test_remaining_tokens_clamps_negative_to_zero(): void
    {
        $result = new RateLimitResult(
            allowed: false,
            remaining: -3,
            limit: 5,
            retryAfter: 10,
            resetAt: time() + 10,
        );

        $this->assertSame(0, $result->remainingTokens());
    }

    public function test_remaining_tokens_after_partial_consumption(): void
    {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 3,
            limit: 10,
            retryAfter: null,
            resetAt: time() + 60,
        );

        $this->assertSame(3, $result->remainingTokens());
    }

    public function test_public_properties_are_readable(): void
    {
        $resetAt = time() + 60;
        $result = new RateLimitResult(
            allowed: true,
            remaining: 7,
            limit: 10,
            retryAfter: null,
            resetAt: $resetAt,
        );

        $this->assertTrue($result->allowed);
        $this->assertSame(7, $result->remaining);
        $this->assertSame(10, $result->limit);
        $this->assertNull($result->retryAfter);
        $this->assertSame($resetAt, $result->resetAt);
    }
}
