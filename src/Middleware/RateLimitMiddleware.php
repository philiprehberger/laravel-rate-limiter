<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter\Middleware;

use Closure;
use Illuminate\Http\Request;
use PhilipRehberger\RateLimiter\RateLimit;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP middleware that applies rate limiting to routes.
 *
 * Register in bootstrap/app.php (Laravel 11+):
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias(['rate-limit' => RateLimitMiddleware::class]);
 *   })
 *
 * Usage on routes:
 *   Route::middleware('rate-limit:100,60,sliding')->group(...)
 *   Route::middleware('rate-limit:50,3600,fixed')->group(...)
 *   Route::middleware('rate-limit:200,60')->group(...)   // uses default algorithm
 *
 * Parameters (all positional, all optional after the first):
 *   1. maxAttempts  — integer, default 60
 *   2. windowSecs   — integer seconds, default 60
 *   3. algorithm    — 'fixed' | 'sliding' | 'token_bucket', default from config
 */
class RateLimitMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $windowSecs = 60, string $algorithm = ''): Response
    {
        $ip = $request->ip() ?? '0.0.0.0';
        $pending = RateLimit::forIp($ip)
            ->allow($maxAttempts)
            ->per($windowSecs);

        if ($algorithm !== '') {
            $pending->algorithm($algorithm);
        }

        $result = $pending->attempt();

        if ($result->denied()) {
            return response()->json(
                [
                    'message' => 'Too Many Requests',
                    'retry_after' => $result->retryAfter,
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                $result->headers(),
            );
        }

        $response = $next($request);

        foreach ($result->headers() as $header => $value) {
            $response->headers->set($header, (string) $value);
        }

        return $response;
    }
}
