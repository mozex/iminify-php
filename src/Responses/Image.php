<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\SizeLimitStatus;
use Mozex\Iminify\Support\Read;

/**
 * One compression: the file sent, the settings it was sent with and, once it has finished,
 * the optimized copy.
 */
class Image
{
    /**
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $id,
        public readonly ImageStatus $status,
        public readonly string $name,
        public readonly ImageFile $original,
        public readonly ?ImageFile $optimized,
        public readonly ?int $savedBytes,
        public readonly ?float $savedPercent,
        public readonly ImageSettings $settings,
        public readonly ?SizeLimitStatus $sizeLimitStatus,
        public readonly ?ContentCredentials $contentCredentials,
        public readonly ?AltText $altText,
        public readonly ?ResponsiveSet $responsive,
        public readonly ?string $scanId,
        public readonly ?string $sourceUrl,
        public readonly ?string $folder,
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
            ImageStatus::tryFrom($attributes['status'] ?? null) ?? ImageStatus::Unknown,
            $attributes['name'] ?? null,
            ImageFile::from($attributes['original'] ?? null),
            isset($attributes['optimized']) ? ImageFile::from($attributes['optimized']) : null,
            $attributes['saved_bytes'] ?? null,
            $attributes['saved_percent'] ?? null,
            ImageSettings::from($attributes['settings'] ?? null),
            isset($attributes['size_limit_status']) ? (SizeLimitStatus::tryFrom($attributes['size_limit_status']) ?? SizeLimitStatus::Unknown) : null,
            isset($attributes['content_credentials']) ? ContentCredentials::from($attributes['content_credentials']) : null,
            isset($attributes['alt_text']) ? AltText::from($attributes['alt_text']) : null,
            isset($attributes['responsive']) ? ResponsiveSet::from($attributes['responsive']) : null,
            $attributes['scan_id'] ?? null,
            $attributes['source_url'] ?? null,
            $attributes['folder'] ?? null,
            $attributes['share_url'] ?? null,
            Read::date($attributes['created_at'] ?? null),
            Read::date($attributes['updated_at'] ?? null),
            $rateLimit ?? new RateLimit,
            $attributes,
        );
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Whether a copy can be downloaded: a `completed` image, or an `already-optimized` one
     * whose upload is handed back.
     */
    public function hasResult(): bool
    {
        return $this->optimized !== null;
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
