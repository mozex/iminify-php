<?php

declare(strict_types=1);

namespace Mozex\Iminify\Spatie;

/**
 * The time one file has left. It counts what was slept as well as what passed, so a sleep
 * that returns at once still ends the waiting.
 */
class Deadline
{
    protected int $started;

    protected int $slept = 0;

    public function __construct(protected int $seconds)
    {
        $this->started = hrtime(true);
    }

    public function secondsLeft(): int
    {
        return (int) floor($this->seconds - max($this->slept, (hrtime(true) - $this->started) / 1e9));
    }

    public function slept(int $seconds): void
    {
        $this->slept += $seconds;
    }
}
