<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where an upload group is. `Expired` is a group whose source never came.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum GroupStatus: string
{
    case AwaitingSource = 'awaiting_source';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Expired = 'expired';
    case Unknown = 'unknown';
}
