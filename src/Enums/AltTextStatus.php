<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where an image's alt text is.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum AltTextStatus: string
{
    case Pending = 'pending';
    case Written = 'written';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Unknown = 'unknown';
}
