<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Enums\ScanStatus;
use Mozex\Iminify\Support\Read;

/**
 * One web page scanned for every image it loads.
 */
class Scan
{
    /**
     * @param  string|null  $note  what the scanner wrote when it finished, or why it failed
     * @param  string|null  $crawlId  the site crawl it's a page of
     * @param  ImageSettings  $settings  what every image it finds is sent with; a scan takes no size limit or responsive widths
     * @param  list<SkippedImage>  $skipped
     * @param  int  $originalSize  in bytes, as are the two after it
     * @param  string  $downloadUrl  the zip's address; it needs your key
     * @param  list<Image>|null  $images  only on a single scan; null in a list or a webhook
     * @param  ScanAudit|null  $audit  the page's report, only on a single scan and once there is one
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly ?string $crawlId,
        public readonly ScanStatus $status,
        public readonly ?string $note,
        public readonly ImageSettings $settings,
        public readonly array $skipped,
        public readonly int $imagesCount,
        public readonly int $imagesInProgress,
        public readonly int $altTextsInProgress,
        public readonly int $imagesFinished,
        public readonly int $originalSize,
        public readonly int $optimizedSize,
        public readonly int $savedBytes,
        public readonly ?float $savedPercent,
        public readonly string $downloadUrl,
        public readonly ?array $images,
        public readonly ?ScanAudit $audit,
        public readonly ?string $shareUrl,
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
            $attributes['crawl_id'] ?? null,
            ScanStatus::tryFrom($attributes['status'] ?? null) ?? ScanStatus::Unknown,
            $attributes['note'] ?? null,
            ImageSettings::from($attributes['settings'] ?? null),
            array_values(array_map(SkippedImage::from(...), $attributes['skipped'] ?? null)),
            $attributes['images_count'] ?? null,
            $attributes['images_in_progress'] ?? null,
            $attributes['alt_texts_in_progress'] ?? null,
            $attributes['images_finished'] ?? null,
            $attributes['original_size'] ?? null,
            $attributes['optimized_size'] ?? null,
            $attributes['saved_bytes'] ?? null,
            $attributes['saved_percent'] ?? null,
            $attributes['download_url'] ?? null,
            isset($attributes['images']) ? array_values(array_map(Image::from(...), $attributes['images'])) : null,
            isset($attributes['audit']) ? ScanAudit::from($attributes['audit']) : null,
            $attributes['share_url'] ?? null,
            Read::date($attributes['created_at'] ?? null),
            Read::date($attributes['updated_at'] ?? null),
            $rateLimit ?? new RateLimit,
            $attributes,
        );
    }

    /**
     * The API's own rule: done once the scanner is through and no image is still compressing.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished() && $this->imagesInProgress === 0;
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
