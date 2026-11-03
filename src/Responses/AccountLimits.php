<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * What the account's plan allows. A daily limit that reads null is unlimited, never zero.
 */
class AccountLimits
{
    /**
     * @param  int|null  $imagesPerDay  null where the plan has no daily limit
     * @param  int|null  $scansPerDay  null where the plan has no daily limit
     * @param  int|null  $filesPerDay  the files upload groups make; null where the plan has no daily limit
     * @param  int|null  $crawlsPerDay  null where the plan has no daily limit
     * @param  int  $maxFileSize  in bytes
     * @param  int  $maxBatch  the most images one batch call takes
     */
    public function __construct(
        public readonly ?int $imagesPerDay,
        public readonly ?int $scansPerDay,
        public readonly int $altTextsPerDay,
        public readonly ?int $filesPerDay,
        public readonly int $imagesPerScan,
        public readonly ?int $crawlsPerDay,
        public readonly int $pagesPerCrawl,
        public readonly int $monitors,
        public readonly int $maxFileSize,
        public readonly int $maxMegapixels,
        public readonly int $compressionsPerMinute,
        public readonly int $scansPerMinute,
        public readonly int $requestsPerMinute,
        public readonly int $maxBatch,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['images_per_day'] ?? null,
            $attributes['scans_per_day'] ?? null,
            $attributes['alt_texts_per_day'] ?? null,
            $attributes['files_per_day'] ?? null,
            $attributes['images_per_scan'] ?? null,
            $attributes['crawls_per_day'] ?? null,
            $attributes['pages_per_crawl'] ?? null,
            $attributes['monitors'] ?? null,
            $attributes['max_file_size'] ?? null,
            $attributes['max_megapixels'] ?? null,
            $attributes['compressions_per_minute'] ?? null,
            $attributes['scans_per_minute'] ?? null,
            $attributes['requests_per_minute'] ?? null,
            $attributes['max_batch'] ?? null,
        );
    }
}
