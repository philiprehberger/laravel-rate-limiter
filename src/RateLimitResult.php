<?php

declare(strict_types=1);

namespace PhilipRehberger\RateLimiter;

class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $limit,
        public readonly ?int $retryAfter,
        public readonly int $resetAt,
    ) {}

    /**
     * Whether the request is allowed to proceed.
     */
    public function allowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Whether the request was denied.
     */
    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * Standard rate limit response headers.
     *
     * @return array<string, string|int>
     */
    public function headers(): array
    {
        $headers = [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => max(0, $this->remaining),
            'X-RateLimit-Reset' => $this->resetAt,
        ];

        if (! $this->allowed && $this->retryAfter !== null) {
            $headers['Retry-After'] = $this->retryAfter;
        }

        return $headers;
    }
}
