<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * One image address in a page's report, in the page's order.
 */
class AuditImage
{
    /**
     * @param  int|null  $bytes  what a browser received
     * @param  string|null  $alt  `missing`, `empty`, `present`
     * @param  list<string>  $findings  `oversized`, `without_dimensions`, `lazy_above_fold`, `eager_below_fold`, `missing_alt`, `lcp_not_prioritized`
     * @param  int|null  $wastedBytes  what an oversized image sends for nothing
     * @param  string  $outcome  `compressed`, `skipped`, `left_out`
     * @param  string|null  $imageId  the image's id, when it was compressed
     * @param  array<string, int>|null  $formatSizes  the bytes it would take in each format that was measured
     * @param  string|null  $suggestedAlt  an AI alt text, for an image the page shows with none
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $format,
        public readonly ?int $bytes,
        public readonly ?int $naturalWidth,
        public readonly ?int $naturalHeight,
        public readonly ?int $renderedWidth,
        public readonly ?int $renderedHeight,
        public readonly ?bool $aboveFold,
        public readonly bool $largestPaint,
        public readonly ?string $alt,
        public readonly array $findings,
        public readonly ?int $wastedBytes,
        public readonly string $outcome,
        public readonly ?string $skipReason,
        public readonly ?string $imageId,
        public readonly ?array $formatSizes,
        public readonly ?string $suggestedAlt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['url'] ?? null,
            $attributes['format'] ?? null,
            $attributes['bytes'] ?? null,
            $attributes['natural_width'] ?? null,
            $attributes['natural_height'] ?? null,
            $attributes['rendered_width'] ?? null,
            $attributes['rendered_height'] ?? null,
            $attributes['above_fold'] ?? null,
            $attributes['largest_paint'] ?? null,
            $attributes['alt'] ?? null,
            $attributes['findings'] ?? null,
            $attributes['wasted_bytes'] ?? null,
            $attributes['outcome'] ?? null,
            $attributes['skip_reason'] ?? null,
            $attributes['image_id'] ?? null,
            isset($attributes['format_sizes']) ? array_column($attributes['format_sizes'], 'bytes', 'format') : null,
            $attributes['suggested_alt'] ?? null,
        );
    }
}
