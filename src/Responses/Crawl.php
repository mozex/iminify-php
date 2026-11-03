<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Enums\CrawlStatus;
use Mozex\Iminify\Support\Read;

/**
 * A whole site scanned page by page, from its sitemap or a list of its pages.
 */
class Crawl
{
    /**
     * @param  string  $source  `sitemap`, `pages`
     * @param  string|null  $monitorId  the weekly check it's a run of
     * @param  ImageSettings  $settings  what every image it finds is sent with; a crawl takes no size limit or responsive widths
     * @param  string|null  $note  why it failed
     * @param  int  $pagesLeftOut  pages past what the plan takes per crawl, which were not scanned
     * @param  int  $originalSize  in bytes, as are the two after it
     * @param  string  $downloadUrl  the zip's address; it needs your key
     * @param  list<CrawlPage>|null  $pages  only on a single crawl; null in a list or on a weekly check's `lastRun`
     * @param  CrawlAudit|null  $audit  the site's report, only on a single crawl and once nothing is at work
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $source,
        public readonly ?string $monitorId,
        public readonly CrawlStatus $status,
        public readonly ?string $note,
        public readonly ImageSettings $settings,
        public readonly int $pagesCount,
        public readonly int $pagesInProgress,
        public readonly int $pagesFailed,
        public readonly int $pagesLeftOut,
        public readonly int $imagesCount,
        public readonly int $imagesInProgress,
        public readonly int $imagesFinished,
        public readonly int $originalSize,
        public readonly int $optimizedSize,
        public readonly int $savedBytes,
        public readonly ?float $savedPercent,
        public readonly string $downloadUrl,
        public readonly ?array $pages,
        public readonly ?CrawlAudit $audit,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly RateLimit $rateLimit,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes, ?RateLimit $rateLimit = null): self
    {
        return new self(
            $attributes['id'] ?? null,
            $attributes['url'] ?? null,
            $attributes['source'] ?? null,
            $attributes['monitor_id'] ?? null,
            CrawlStatus::tryFrom($attributes['status'] ?? null) ?? CrawlStatus::Unknown,
            $attributes['note'] ?? null,
            ImageSettings::from($attributes['settings'] ?? null),
            $attributes['pages_count'] ?? null,
            $attributes['pages_in_progress'] ?? null,
            $attributes['pages_failed'] ?? null,
            $attributes['pages_left_out'] ?? null,
            $attributes['images_count'] ?? null,
            $attributes['images_in_progress'] ?? null,
            $attributes['images_finished'] ?? null,
            $attributes['original_size'] ?? null,
            $attributes['optimized_size'] ?? null,
            $attributes['saved_bytes'] ?? null,
            $attributes['saved_percent'] ?? null,
            $attributes['download_url'] ?? null,
            isset($attributes['pages']) ? array_values(array_map(fn (array $page): CrawlPage => CrawlPage::from($page), $attributes['pages'])) : null,
            isset($attributes['audit']) ? CrawlAudit::from($attributes['audit']) : null,
            Read::date($attributes['created_at'] ?? null),
            Read::date($attributes['updated_at'] ?? null),
            $rateLimit ?? new RateLimit,
            $attributes,
        );
    }

    /**
     * The API's own rule: done once it has stopped and no page or image is still at work.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished() && $this->pagesInProgress === 0 && $this->imagesInProgress === 0;
    }

    /**
     * The answer as the API sent it, field names and any field this client doesn't know included.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
