<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * How the optimized copy came out against the size limit.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum SizeLimitStatus: string
{
    case Under = 'under';
    case Resized = 'resized';
    case Over = 'over';
    case Unknown = 'unknown';
}
