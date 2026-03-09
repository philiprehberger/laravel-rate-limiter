<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class TokenBucketTest extends TestCase
{
    public function test_allows_requests_up_to_capacity(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $result = RateLimit::for('tb-allow')
                ->allow(5)
                ->perMinute()
                ->algorithm('token_bucket')
                ->attempt();

            $this->assertTrue($result->allowed(), "Request {$i} should be allowed");
        }
    }

    public function test_blocks_when_bucket_is_empty(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimit::for('tb-block')
                ->allow(5)
                ->perMinute()
                ->algorithm('token_bucket')
                ->attempt();
        }

        $result = RateLimit::for('tb-block')
            ->allow(5)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $this->assertFalse($result->allowed());
        $this->assertNotNull($result->retryAfter);
        $this->assertGreaterThan(0, $result->retryAfter);
    }

    public function test_remaining_decrements_after_each_consume(): void
    {
        $result1 = RateLimit::for('tb-decrement')
            ->allow(10)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $result2 = RateLimit::for('tb-decrement')
            ->allow(10)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $this->assertTrue($result1->allowed());
        $this->assertTrue($result2->allowed());
        $this->assertGreaterThan($result2->remaining, $result1->remaining);
    }

    public function test_check_does_not_consume_tokens(): void
    {
        for ($i = 0; $i < 4; $i++) {
            RateLimit::for('tb-check')
                ->allow(5)
                ->perMinute()
                ->algorithm('token_bucket')
                ->attempt();
        }

        // Two checks should not push us over the limit
        RateLimit::for('tb-check')->allow(5)->perMinute()->algorithm('token_bucket')->check();
        RateLimit::for('tb-check')->allow(5)->perMinute()->algorithm('token_bucket')->check();

        // 5th attempt should succeed
        $result = RateLimit::for('tb-check')
            ->allow(5)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $this->assertTrue($result->allowed());
    }

    public function test_fresh_bucket_starts_at_full_capacity(): void
    {
        $result = RateLimit::for('tb-fresh')
            ->allow(100)
            ->perHour()
            ->algorithm('token_bucket')
            ->check();

        $this->assertTrue($result->allowed());
        $this->assertSame(100, $result->remaining);
    }

    public function test_result_headers_are_present(): void
    {
        $result = RateLimit::for('tb-headers')
            ->allow(50)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $headers = $result->headers();
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertSame(50, $headers['X-RateLimit-Limit']);
    }

    public function test_retry_after_header_present_when_denied(): void
    {
        for ($i = 0; $i < 3; $i++) {
            RateLimit::for('tb-retry-header')
                ->allow(3)
                ->perMinute()
                ->algorithm('token_bucket')
                ->attempt();
        }

        $result = RateLimit::for('tb-retry-header')
            ->allow(3)
            ->perMinute()
            ->algorithm('token_bucket')
            ->attempt();

        $this->assertFalse($result->allowed());
        $headers = $result->headers();
        $this->assertArrayHasKey('Retry-After', $headers);
    }
}
