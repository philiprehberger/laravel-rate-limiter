<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class SlidingWindowTest extends TestCase
{
    public function test_allows_requests_up_to_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $result = RateLimit::for('sw-test-allow')
                ->allow(5)
                ->perMinute()
                ->algorithm('sliding')
                ->attempt();

            $this->assertTrue($result->allowed(), "Request {$i} should be allowed");
        }
    }

    public function test_blocks_request_over_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimit::for('sw-test-block')
                ->allow(5)
                ->perMinute()
                ->algorithm('sliding')
                ->attempt();
        }

        $result = RateLimit::for('sw-test-block')
            ->allow(5)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertFalse($result->allowed());
        $this->assertSame(0, $result->remaining);
        $this->assertNotNull($result->retryAfter);
    }

    public function test_check_does_not_consume_tokens(): void
    {
        for ($i = 0; $i < 3; $i++) {
            RateLimit::for('sw-check-key')
                ->allow(5)
                ->perMinute()
                ->algorithm('sliding')
                ->attempt();
        }

        RateLimit::for('sw-check-key')->allow(5)->perMinute()->algorithm('sliding')->check();
        RateLimit::for('sw-check-key')->allow(5)->perMinute()->algorithm('sliding')->check();

        // 4th attempt should still succeed
        $result = RateLimit::for('sw-check-key')
            ->allow(5)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertTrue($result->allowed());
    }

    public function test_remaining_count_is_accurate(): void
    {
        $limit = 10;
        $consumed = 6;

        for ($i = 0; $i < $consumed; $i++) {
            RateLimit::for('sw-remaining')
                ->allow($limit)
                ->perMinute()
                ->algorithm('sliding')
                ->attempt();
        }

        $result = RateLimit::for('sw-remaining')
            ->allow($limit)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame($limit - $consumed - 1, $result->remaining);
    }

    public function test_sliding_window_prunes_expired_timestamps(): void
    {
        // This test uses the array cache and verifies that the sliding window
        // correctly treats a fresh key as having zero history.
        // (Full time-travel testing requires DI for clock; here we verify
        //  that a brand-new key starts at full capacity.)
        $result = RateLimit::for('sw-fresh-key')
            ->allow(3)
            ->perSecond()
            ->algorithm('sliding')
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(2, $result->remaining);
    }

    public function test_result_limit_matches_configured_limit(): void
    {
        $result = RateLimit::for('sw-limit-field')
            ->allow(42)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertSame(42, $result->limit);
    }

    public function test_denied_result_has_no_retry_after_when_allowed(): void
    {
        $result = RateLimit::for('sw-no-retry')
            ->allow(10)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertNull($result->retryAfter);
    }

    public function test_allowed_false_when_limit_is_zero(): void
    {
        // A limit of 0 should immediately block every request.
        $result = RateLimit::for('sw-zero-limit')
            ->allow(0)
            ->perMinute()
            ->algorithm('sliding')
            ->attempt();

        $this->assertFalse($result->allowed());
    }
}
