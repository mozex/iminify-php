<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Whether the optimized copy still carries the original's content credentials.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum ContentCredentialsStatus: string
{
    case Removed = 'removed';
    case Kept = 'kept';
    case Unknown = 'unknown';
}
