<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * Where to cut a cropped output of an upload group, given once across and once down.
 */
enum CropAnchor: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
}
