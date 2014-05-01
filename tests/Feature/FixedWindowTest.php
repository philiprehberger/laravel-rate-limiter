<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class FixedWindowTest extends TestCase
{
    public function test_allows_requests_up_to_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $result = RateLimit::for('fw-test-allow')
                ->allow(5)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();

            $this->assertTrue($result->allowed(), "Request {$i} should be allowed");
        }
    }

    public function test_blocks_request_over_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimit::for('fw-test-block')
                ->allow(5)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();
        }

        $result = RateLimit::for('fw-test-block')
            ->allow(5)
            ->perMinute()
            ->algorithm('fixed')
            ->attempt();

        $this->assertFalse($result->allowed());
        $this->assertSame(0, $result->remaining);
        $this->assertNotNull($result->retryAfter);
        $this->assertGreaterThan(0, $result->retryAfter);
    }

    public function test_remaining_decrements_correctly(): void
    {
        $limit = 10;

        for ($i = 0; $i < 7; $i++) {
            $result = RateLimit::for('fw-test-remaining')
                ->allow($limit)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();
        }

        $result = RateLimit::for('fw-test-remaining')
            ->allow($limit)
            ->perMinute()
            ->algorithm('fixed')
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(2, $result->remaining);
    }

    public function test_check_does_not_consume_tokens(): void
    {
        // Pre-consume 3 of 5
        for ($i = 0; $i < 3; $i++) {
            RateLimit::for('fw-test-check')
                ->allow(5)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();
        }

        // Check twice — should not advance the counter
        RateLimit::for('fw-test-check')->allow(5)->perMinute()->algorithm('fixed')->check();
        RateLimit::for('fw-test-check')->allow(5)->perMinute()->algorithm('fixed')->check();

        // 4th attempt should still succeed (counter is at 3)
        $result = RateLimit::for('fw-test-check')
            ->allow(5)
            ->perMinute()
            ->algorithm('fixed')
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(1, $result->remaining);
    }

    public function test_check_reports_blocked_state_correctly(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimit::for('fw-test-check-blocked')
                ->allow(5)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();
        }

        $result = RateLimit::for('fw-test-check-blocked')
            ->allow(5)
            ->perMinute()
            ->algorithm('fixed')
            ->check();

        $this->assertFalse($result->allowed());
        $this->assertSame(0, $result->remaining);
    }

    public function test_result_headers_contain_required_keys(): void
    {
        $result = RateLimit::for('fw-test-headers')
            ->allow(100)
            ->perMinute()
            ->algorithm('fixed')
            ->attempt();

        $headers = $result->headers();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertSame(100, $headers['X-RateLimit-Limit']);
    }

    public function test_result_headers_include_retry_after_when_blocked(): void
    {
        for ($i = 0; $i < 3; $i++) {
            RateLimit::for('fw-test-retry-header')
                ->allow(3)
                ->perMinute()
                ->algorithm('fixed')
                ->attempt();
        }

        $result = RateLimit::for('fw-test-retry-header')
            ->allow(3)
            ->perMinute()
            ->algorithm('fixed')
            ->attempt();

        $this->assertFalse($result->allowed());
        $headers = $result->headers();
        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertGreaterThan(0, $headers['Retry-After']);
    }

    public function test_different_keys_are_independent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimit::for('fw-key-a')->allow(5)->perMinute()->algorithm('fixed')->attempt();
        }

        // key-b should still be at full capacity
        $result = RateLimit::for('fw-key-b')->allow(5)->perMinute()->algorithm('fixed')->attempt();
        $this->assertTrue($result->allowed());
        $this->assertSame(4, $result->remaining);
    }
}
