<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use PhilipRehberger\RateLimiter\RateLimit;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class PerEntityTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Per-user limiting
    // -------------------------------------------------------------------------

    public function test_per_user_limiting_uses_user_id_in_key(): void
    {
        $userA = $this->makeUser(1);
        $userB = $this->makeUser(2);

        // Exhaust userA's budget
        for ($i = 0; $i < 3; $i++) {
            RateLimit::forUser($userA)->allow(3)->perMinute()->attempt();
        }

        // userA is blocked
        $resultA = RateLimit::forUser($userA)->allow(3)->perMinute()->attempt();
        $this->assertFalse($resultA->allowed());

        // userB is still allowed
        $resultB = RateLimit::forUser($userB)->allow(3)->perMinute()->attempt();
        $this->assertTrue($resultB->allowed());
    }

    public function test_per_user_limits_are_independent_per_user(): void
    {
        $users = array_map(fn (int $id) => $this->makeUser($id), range(10, 15));

        foreach ($users as $user) {
            $result = RateLimit::forUser($user)->allow(10)->perMinute()->attempt();
            $this->assertTrue($result->allowed());
        }
    }

    // -------------------------------------------------------------------------
    // Per-IP limiting
    // -------------------------------------------------------------------------

    public function test_per_ip_limiting_uses_ip_in_key(): void
    {
        $ipA = '192.168.1.1';
        $ipB = '192.168.1.2';

        for ($i = 0; $i < 5; $i++) {
            RateLimit::forIp($ipA)->allow(5)->perMinute()->attempt();
        }

        $resultA = RateLimit::forIp($ipA)->allow(5)->perMinute()->attempt();
        $this->assertFalse($resultA->allowed());

        $resultB = RateLimit::forIp($ipB)->allow(5)->perMinute()->attempt();
        $this->assertTrue($resultB->allowed());
    }

    public function test_per_ip_limits_are_independent(): void
    {
        $ips = ['10.0.0.1', '10.0.0.2', '10.0.0.3'];

        foreach ($ips as $ip) {
            $result = RateLimit::forIp($ip)->allow(5)->perMinute()->attempt();
            $this->assertTrue($result->allowed());
        }
    }

    // -------------------------------------------------------------------------
    // Composite / action keys
    // -------------------------------------------------------------------------

    public function test_composite_key_via_on_method(): void
    {
        $user = $this->makeUser(99);

        // Exhaust the login limit
        for ($i = 0; $i < 5; $i++) {
            RateLimit::forUser($user)->on('login')->allow(5)->perMinute()->attempt();
        }

        $loginResult = RateLimit::forUser($user)->on('login')->allow(5)->perMinute()->attempt();
        $this->assertFalse($loginResult->allowed());

        // The 'export' action is a different composite key — should still be allowed
        $exportResult = RateLimit::forUser($user)->on('export')->allow(5)->perMinute()->attempt();
        $this->assertTrue($exportResult->allowed());
    }

    public function test_on_creates_separate_limits_for_each_action(): void
    {
        $key = 'composite-entity';
        $actions = ['read', 'write', 'delete'];

        foreach ($actions as $action) {
            for ($i = 0; $i < 3; $i++) {
                RateLimit::for($key)->on($action)->allow(3)->perMinute()->attempt();
            }
        }

        // Each action should now be blocked independently
        foreach ($actions as $action) {
            $result = RateLimit::for($key)->on($action)->allow(3)->perMinute()->attempt();
            $this->assertFalse($result->allowed(), "Action '{$action}' should be blocked");
        }
    }

    // -------------------------------------------------------------------------
    // Arbitrary key
    // -------------------------------------------------------------------------

    public function test_for_uses_arbitrary_string_key(): void
    {
        $result = RateLimit::for('my-custom-key')->allow(10)->perMinute()->attempt();
        $this->assertTrue($result->allowed());
        $this->assertSame(9, $result->remaining);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(int $id): Authenticatable
    {
        return new class($id) implements Authenticatable
        {
            public function __construct(private readonly int $id) {}

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }
}
