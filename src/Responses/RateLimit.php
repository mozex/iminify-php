<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * The limit headers of one answer. Each value is null when the answer didn't carry it.
 */
class RateLimit
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
        public readonly ?int $retryAfter = null,
    ) {}

    /**
     * @param  array<string, string>  $headers  with lowercase names
     */
    public static function fromHeaders(array $headers): self
    {
        return new self(
            self::number($headers['x-ratelimit-limit'] ?? null),
            self::number($headers['x-ratelimit-remaining'] ?? null),
            self::number($headers['retry-after'] ?? null),
        );
    }

    protected static function number(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
