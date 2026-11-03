<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * A site's image report, added up from the reports of its pages.
 */
class CrawlAudit
{
    /**
     * @param  int  $oversizedBytes  about how many bytes the oversized images would shed at twice the size they're shown
     * @param  int  $bytes  what a browser downloaded for the site's images
     */
    public function __construct(
        public readonly int $pagesAudited,
        public readonly int $imagesFound,
        public readonly int $imagesToFix,
        public readonly AuditFindings $findings,
        public readonly int $oversizedBytes,
        public readonly int $bytes,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['pages_audited'] ?? null,
            $attributes['images_found'] ?? null,
            $attributes['images_to_fix'] ?? null,
            AuditFindings::from($attributes['findings'] ?? null),
            $attributes['oversized_bytes'] ?? null,
            $attributes['bytes'] ?? null,
        );
    }
}
