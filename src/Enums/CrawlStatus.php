<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where a site crawl is.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum CrawlStatus: string
{
    case Queued = 'queued';
    case Reading = 'reading';
    case Scanning = 'scanning';
    case Completed = 'completed';
    case Failed = 'failed';
    case Unknown = 'unknown';

    /**
     * Whether the crawl has stopped. Its pages and images can still be at work, so ask
     * `Crawl::isFinished()` for the whole of it.
     */
    public function isFinished(): bool
    {
        return $this !== self::Queued && $this !== self::Reading && $this !== self::Scanning;
    }
}
