<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class CostBasedLimitingTest extends TestCase
{
    public function test_cost_of_one_is_default_behaviour(): void
    {
        $result = RateLimit::for('cost-default')
            ->allow(10)
            ->perMinute()
            ->cost(1)
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(9, $result->remaining);
    }

    public function test_high_cost_operation_consumes_multiple_tokens(): void
    {
        // 10 slots, one operation costs 4 → 6 remaining
        $result = RateLimit::for('cost-high-fixed')
            ->allow(10)
            ->perMinute()
            ->algorithm('fixed')
            ->cost(4)
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(6, $result->remaining);
    }

    public function test_cost_exceeding_limit_is_blocked(): void
    {
        // Limit is 3, cost is 5 → should immediately block
        $result = RateLimit::for('cost-exceeds-limit-fixed')
            ->allow(3)
            ->perMinute()
            ->algorithm('fixed')
            ->cost(5)
            ->attempt();

        $this->assertFalse($result->allowed());
    }

    public function test_successive_high_cost_operations_deplete_budget_fixed(): void
    {
        $key = 'cost-deplete-fixed';

        // 10 tokens, cost 4 → first succeeds (6 left), second succeeds (2 left)
        $r1 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('fixed')->cost(4)->attempt();
        $r2 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('fixed')->cost(4)->attempt();
        $r3 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('fixed')->cost(4)->attempt();

        $this->assertTrue($r1->allowed());
        $this->assertTrue($r2->allowed());
        $this->assertFalse($r3->allowed()); // only 2 tokens left, cost is 4
    }

    public function test_cost_based_limiting_with_sliding_window(): void
    {
        $key = 'cost-sliding';

        // 10 tokens, cost 3 → can make 3 requests (uses 9 tokens), 4th blocked
        for ($i = 0; $i < 3; $i++) {
            $result = RateLimit::for($key)
                ->allow(10)
                ->perMinute()
                ->algorithm('sliding')
                ->cost(3)
                ->attempt();
            $this->assertTrue($result->allowed(), "Request {$i} should be allowed");
        }

        $result = RateLimit::for($key)
            ->allow(10)
            ->perMinute()
            ->algorithm('sliding')
            ->cost(3)
            ->attempt();

        $this->assertFalse($result->allowed());
    }

    public function test_cost_based_limiting_with_token_bucket(): void
    {
        $key = 'cost-token-bucket';

        // 10 capacity, cost 5 → can make 2 requests
        $r1 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('token_bucket')->cost(5)->attempt();
        $r2 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('token_bucket')->cost(5)->attempt();
        $r3 = RateLimit::for($key)->allow(10)->perMinute()->algorithm('token_bucket')->cost(5)->attempt();

        $this->assertTrue($r1->allowed());
        $this->assertTrue($r2->allowed());
        $this->assertFalse($r3->allowed());
    }

    public function test_cost_is_clamped_to_minimum_of_one(): void
    {
        // cost(0) should be treated as cost(1)
        $result = RateLimit::for('cost-clamp')
            ->allow(5)
            ->perMinute()
            ->cost(0)
            ->attempt();

        $this->assertTrue($result->allowed());
        $this->assertSame(4, $result->remaining);
    }
}
