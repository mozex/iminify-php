<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * A format to make each output of an upload group in. `Original` is the source's own.
 */
enum GroupFormat: string
{
    case Original = 'original';
    case Webp = 'webp';
    case Avif = 'avif';
}
