<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * How many images a page or a site has with each problem.
 */
class AuditFindings
{
    public function __construct(
        public readonly int $oversized,
        public readonly int $withoutDimensions,
        public readonly int $lazyAboveFold,
        public readonly int $eagerBelowFold,
        public readonly int $missingAlt,
        public readonly int $lcpNotPrioritized,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['oversized'] ?? null,
            $attributes['without_dimensions'] ?? null,
            $attributes['lazy_above_fold'] ?? null,
            $attributes['eager_below_fold'] ?? null,
            $attributes['missing_alt'] ?? null,
            $attributes['lcp_not_prioritized'] ?? null,
        );
    }
}
