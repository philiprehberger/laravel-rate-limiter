<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use InvalidArgumentException;
use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class ValidationTest extends TestCase
{
    public function test_allow_zero_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('test')->allow(0)->perMinute()->attempt();
    }

    public function test_allow_negative_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('test')->allow(-5)->perMinute()->attempt();
    }

    public function test_per_zero_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('test')->allow(10)->per(0)->attempt();
    }

    public function test_per_negative_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('test')->allow(10)->per(-1)->attempt();
    }

    public function test_invalid_algorithm_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('test')->allow(10)->perMinute()->algorithm('invalid')->attempt();
    }

    public function test_valid_allow_and_per_values_accepted(): void
    {
        $result = RateLimit::for('valid-test')->allow(1)->per(1)->attempt();

        $this->assertTrue($result->allowed());
    }

    public function test_all_algorithm_names_resolve_correctly(): void
    {
        foreach (['fixed', 'sliding', 'token_bucket'] as $algo) {
            $result = RateLimit::for("algo-{$algo}")
                ->allow(10)
                ->perMinute()
                ->algorithm($algo)
                ->attempt();

            $this->assertTrue($result->allowed(), "Algorithm '{$algo}' should work");
        }
    }

    public function test_middleware_with_zero_attempts_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimit::for('mw-test')->allow(0)->perMinute()->attempt();
    }
}
