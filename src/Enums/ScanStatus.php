<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where a page scan is. A scan ends `NoImageFound` when it found nothing it could take.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum ScanStatus: string
{
    case Queued = 'queued';
    case Scanning = 'scanning';
    case Completed = 'completed';
    case Failed = 'failed';
    case NoImageFound = 'no-image-found';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    /**
     * Whether the scanner is done with the page. Its images can still be compressing, so
     * ask `Scan::isFinished()` for the whole of it.
     */
    public function isFinished(): bool
    {
        return $this !== self::Queued && $this !== self::Scanning;
    }
}
