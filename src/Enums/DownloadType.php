<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Which of an image's two files to download.
 */
enum DownloadType: string
{
    case Optimized = 'optimized';
    case Original = 'original';
}
