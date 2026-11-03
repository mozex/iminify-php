<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Enums\ScanStatus;

/**
 * One page of a site crawl. It's a page scan: read it through `scans()` with its id for its
 * images, what it skipped and its own report.
 */
class CrawlPage
{
    /**
     * @param  int|null  $imagesToFix  how many of its images have a problem, once its report exists
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly ScanStatus $status,
        public readonly ?string $note,
        public readonly int $imagesCount,
        public readonly int $imagesInProgress,
        public readonly ?int $imagesToFix,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'] ?? null,
            $attributes['url'] ?? null,
            ScanStatus::tryFrom($attributes['status'] ?? null) ?? ScanStatus::Unknown,
            $attributes['note'] ?? null,
            $attributes['images_count'] ?? null,
            $attributes['images_in_progress'] ?? null,
            $attributes['images_to_fix'] ?? null,
        );
    }
}
