<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Support\Read;

/**
 * A weekly check of a site, made from one of its crawls.
 */
class Monitor
{
    /**
     * @param  string  $source  `sitemap`, `pages`
     * @param  string|null  $crawlId  the crawl it was made from, until that's deleted
     * @param  ImageSettings  $settings  what every image a run finds is sent with
     * @param  bool  $emails  whether a run that finds new or changed images, or scans no page at all, emails you
     * @param  bool  $paused  true while the plan doesn't cover it; it's kept, and runs again once the plan does
     * @param  Crawl|null  $lastRun  the newest run, without its pages: read it through `crawls()` for those
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $source,
        public readonly ?string $crawlId,
        public readonly ImageSettings $settings,
        public readonly bool $emails,
        public readonly bool $paused,
        public readonly DateTimeImmutable $nextRunAt,
        public readonly ?Crawl $lastRun,
        public readonly DateTimeImmutable $createdAt,
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
            $attributes['crawl_id'] ?? null,
            ImageSettings::from($attributes['settings'] ?? null),
            $attributes['emails'] ?? null,
            $attributes['paused'] ?? null,
            Read::date($attributes['next_run_at'] ?? null),
            isset($attributes['last_run']) ? Crawl::from($attributes['last_run']) : null,
            Read::date($attributes['created_at'] ?? null),
            $rateLimit ?? new RateLimit,
            $attributes,
        );
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
