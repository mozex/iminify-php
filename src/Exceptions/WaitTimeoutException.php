<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * Waiting ran out of time. The work goes on at Iminify: `$last` holds the record as last
 * read, null when nothing was read, and asking again later finds it further along.
 */
class WaitTimeoutException extends IminifyException
{
    public function __construct(string $message, public readonly mixed $last = null)
    {
        parent::__construct($message);
    }

    public static function after(int $seconds, string $what, mixed $last): self
    {
        return new self("{$what} didn't finish within {$seconds} seconds.", $last);
    }
}
