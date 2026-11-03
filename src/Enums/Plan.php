<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * The plan an account is on. `Trial` is the days of Pro an account gets once.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum Plan: string
{
    case Free = 'free';
    case Trial = 'trial';
    case Pro = 'pro';
    case Max = 'max';
    case Unknown = 'unknown';
}
