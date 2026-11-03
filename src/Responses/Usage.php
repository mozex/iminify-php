<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Support\Read;

/**
 * How much of one daily limit is gone. The window is a rolling 24 hours from the first use.
 */
class Usage
{
    /**
     * @param  int|null  $remaining  null where the plan has no limit on this, which isn't zero; ask `isUnlimited()` or `isSpent()` rather than comparing it
     * @param  DateTimeImmutable|null  $resetsAt  null while nothing has been used
     */
    public function __construct(
        public readonly int $used,
        public readonly ?int $remaining,
        public readonly ?DateTimeImmutable $resetsAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['used'] ?? null,
            $attributes['remaining'] ?? null,
            isset($attributes['resets_at']) ? Read::date($attributes['resets_at']) : null,
        );
    }

    public function isUnlimited(): bool
    {
        return $this->remaining === null;
    }

    public function isSpent(): bool
    {
        return $this->remaining !== null && $this->remaining <= 0;
    }
}
