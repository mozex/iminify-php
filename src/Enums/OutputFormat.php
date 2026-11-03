<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * The format to deliver an image in. Leave it out to keep the upload's.
 */
enum OutputFormat: string
{
    case Auto = 'auto';
    case Webp = 'webp';
    case Avif = 'avif';
    case Png = 'png';
    case Jpg = 'jpg';
}
