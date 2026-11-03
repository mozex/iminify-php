<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where an image's responsive sizes are.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum ResponsiveStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Unknown = 'unknown';
}
