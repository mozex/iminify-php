<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Support\Read;

/**
 * A page's image report: what a browser really received and how the page used it.
 */
class ScanAudit
{
    /**
     * @param  bool  $rendered  whether a browser opened the page; false when only its HTML could be read, and then every finding count is 0
     * @param  int  $oversizedBytes  about how many bytes the oversized images would shed at twice the size they're shown
     * @param  int  $bytes  what a browser downloaded for the images listed
     * @param  array<string, int>  $formats  how many images arrived in each format, the commonest first
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
            Read::byFormat($attributes['formats'] ?? null, 'count'),
            array_values(array_map(AuditImage::from(...), $attributes['images'] ?? null)),
        );
    }
}
