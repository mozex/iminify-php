<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * `Background` is for bulk work nobody waits on: it runs after every image sent without it.
 */
enum Priority: string
{
    case Interactive = 'interactive';
    case Background = 'background';
}
