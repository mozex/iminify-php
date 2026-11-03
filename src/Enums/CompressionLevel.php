<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * How hard to compress.
 */
enum CompressionLevel: string
{
    case None = 'none';
    case Lossless = 'lossless';
    case Ultra = 'ultra';
    case Smart = 'smart';
}
