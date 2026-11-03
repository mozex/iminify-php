<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where an image is.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum ImageStatus: string
{
    case Queued = 'queued';
    case Optimizing = 'optimizing';
    case Completed = 'completed';
    case AlreadyOptimized = 'already-optimized';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    /**
     * The API's own rule: an image has finished once it's no longer queued or optimizing.
     */
    public function isFinished(): bool
    {
        return $this !== self::Queued && $this !== self::Optimizing;
    }
}
