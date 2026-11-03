<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * A page's image report: what a browser really received and how the page used it.
 */
class ScanAudit
{
    /**
     * @param  bool  $rendered  whether a browser rendered the page, which every measure of size on screen needs
     * @param  int  $oversizedBytes  what the page's oversized images send for nothing
     * @param  int  $bytes  what the page's images weigh
     * @param  array<string, int>  $formats  how many images in each format
     * @param  list<AuditImage>  $images
     */
    public function __construct(
        public readonly bool $rendered,
        public readonly int $imagesFound,
        public readonly ?LargestPaint $largestPaint,
        public readonly AuditFindings $findings,
        public readonly int $oversizedBytes,
        public readonly int $bytes,
        public readonly array $formats,
        public readonly array $images,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['rendered'] ?? null,
            $attributes['images_found'] ?? null,
            isset($attributes['largest_paint']) ? LargestPaint::from($attributes['largest_paint']) : null,
            AuditFindings::from($attributes['findings'] ?? null),
            $attributes['oversized_bytes'] ?? null,
            $attributes['bytes'] ?? null,
            array_column($attributes['formats'] ?? null, 'count', 'format'),
            array_values(array_map(fn (array $image): AuditImage => AuditImage::from($image), $attributes['images'] ?? null)),
        );
    }
}
