<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PhilipRehberger\RateLimiter\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('rate-limit:3,60,fixed')->get('/test-route', fn () => response()->json(['ok' => true]));
    }

    public function test_allows_requests_within_limit(): void
    {
        $response = $this->get('/test-route', ['REMOTE_ADDR' => '1.2.3.4']);
        $response->assertStatus(200);
    }

    public function test_rate_limit_headers_are_added_to_allowed_responses(): void
    {
        $response = $this->get('/test-route', ['REMOTE_ADDR' => '1.2.3.5']);

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }

    public function test_returns_429_when_limit_exceeded(): void
    {
        // Exhaust the 3-request limit for this IP
        for ($i = 0; $i < 3; $i++) {
            $this->get('/test-route', ['REMOTE_ADDR' => '5.6.7.8']);
        }

        $response = $this->get('/test-route', ['REMOTE_ADDR' => '5.6.7.8']);
        $response->assertStatus(429);
    }

    public function test_429_response_contains_retry_after_header(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->get('/test-route', ['REMOTE_ADDR' => '9.10.11.12']);
        }

        $response = $this->get('/test-route', ['REMOTE_ADDR' => '9.10.11.12']);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_429_response_body_contains_message(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->get('/test-route', ['REMOTE_ADDR' => '13.14.15.16']);
        }

        $response = $this->get('/test-route', ['REMOTE_ADDR' => '13.14.15.16']);

        $response->assertStatus(429);
        $response->assertJsonPath('message', 'Too Many Requests');
        $response->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_different_ips_have_independent_limits(): void
    {
        // Exhaust limit for IP A
        for ($i = 0; $i < 3; $i++) {
            $this->get('/test-route', ['REMOTE_ADDR' => '100.0.0.1']);
        }
        $responseA = $this->get('/test-route', ['REMOTE_ADDR' => '100.0.0.1']);
        $responseA->assertStatus(429);

        // IP B should still be allowed
        $responseB = $this->get('/test-route', ['REMOTE_ADDR' => '100.0.0.2']);
        $responseB->assertStatus(200);
    }

    public function test_middleware_uses_default_algorithm_when_not_specified(): void
    {
        Route::middleware('rate-limit:5,60')->get('/test-default-algo', fn () => response()->json(['ok' => true]));

        $response = $this->get('/test-default-algo', ['REMOTE_ADDR' => '200.0.0.1']);
        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', '5');
    }

    public function test_x_rate_limit_limit_header_matches_configured_limit(): void
    {
        $response = $this->get('/test-route', ['REMOTE_ADDR' => '50.50.50.50']);
        $response->assertHeader('X-RateLimit-Limit', '3');
    }
}
