<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * One image address in a page's report, in the page's order.
 */
class AuditImage
{
    /**
     * @param  int|null  $bytes  what a browser downloaded for it; null when it didn't load as an image
     * @param  string|null  $alt  `missing`, `empty`, `present`
     * @param  list<string>  $findings  `oversized`, `without_dimensions`, `lazy_above_fold`, `eager_below_fold`, `missing_alt`, `lcp_not_prioritized`
     * @param  int|null  $wastedBytes  about how many bytes it would shed at twice the size it's shown, when it's oversized
     * @param  string  $outcome  `compressed`, `skipped`, `left_out`
     * @param  string|null  $imageId  the id of its compressed image; for a `duplicate`, of the image it shares its bytes with
     * @param  array<string, int>|null  $formatSizes  what the compressed file came to in each format, when the scan's format was `auto` and more than one was tried
     * @param  string|null  $suggestedAlt  an AI alt text, for an image the page shows without one
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
