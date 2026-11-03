<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Enums\GroupStatus;

/**
 * One image and every size and format made from it, counted as one image.
 */
class Group
{
    /**
     * @param  DateTimeImmutable|null  $uploadBy  when the source has to arrive by, while it's awaited
     * @param  int  $files  how many files the group makes, which count against the daily files
     * @param  list<GroupOutput>  $outputs  empty until the source arrives
     * @param  string|null  $downloadUrl  the zip's address, once the group has finished; it needs your key
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly GroupStatus $status,
        public readonly ?DateTimeImmutable $uploadBy,
        public readonly GroupSource $source,
        public readonly GroupSettings $settings,
        public readonly int $files,
        public readonly array $outputs,
        public readonly ?AltText $altText,
        public readonly ?string $downloadUrl,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $receivedAt,
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
            $attributes['name'] ?? null,
            GroupStatus::tryFrom($attributes['status'] ?? null) ?? GroupStatus::Unknown,
            isset($attributes['upload_by']) ? new DateTimeImmutable($attributes['upload_by']) : null,
            GroupSource::from($attributes['source'] ?? null),
            GroupSettings::from($attributes['settings'] ?? null),
            $attributes['files'] ?? null,
            array_values(array_map(fn (array $output): GroupOutput => GroupOutput::from($output), $attributes['outputs'] ?? null)),
            isset($attributes['alt_text']) ? AltText::from($attributes['alt_text']) : null,
            $attributes['download_url'] ?? null,
            new DateTimeImmutable($attributes['created_at'] ?? null),
            isset($attributes['received_at']) ? new DateTimeImmutable($attributes['received_at']) : null,
            $rateLimit ?? new RateLimit,
            $attributes,
        );
    }

    /**
     * Whether its files are being made, the one state worth waiting on: nothing but your
     * own `sendSource()` moves a group on from waiting for its source.
     */
    public function isInProgress(): bool
    {
        return $this->status === GroupStatus::InProgress;
    }

    /**
     * Whether its files are ready to download.
     */
    public function isFinished(): bool
    {
        return $this->status === GroupStatus::Finished;
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
