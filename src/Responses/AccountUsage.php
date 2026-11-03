<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * How much of each daily limit is gone.
 */
class AccountUsage
{
    public function __construct(
        public readonly Usage $images,
        public readonly Usage $scans,
        public readonly Usage $crawls,
        public readonly Usage $altTexts,
        public readonly Usage $files,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            Usage::from($attributes['images'] ?? null),
            Usage::from($attributes['scans'] ?? null),
            Usage::from($attributes['crawls'] ?? null),
            Usage::from($attributes['alt_texts'] ?? null),
            Usage::from($attributes['files'] ?? null),
        );
    }
}
