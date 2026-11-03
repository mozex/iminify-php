<?php

declare(strict_types=1);

use Mozex\Iminify\Spatie\Deadline;

function deadlineStarted(int $secondsAgo): Deadline
{
    $deadline = new Deadline(10);

    (fn (): float => $this->started -= $secondsAgo * 1_000_000_000)->call($deadline);

    return $deadline;
}

describe('a deadline', function () {
    it('counts what was slept, for a sleep that returned at once', function () {
        $deadline = new Deadline(10);

        $deadline->slept(4);
        $deadline->slept(4);

        expect($deadline->secondsLeft())->toBe(2);
    });

    it('counts the time that really passed, for work that was no sleep at all', function () {
        expect(deadlineStarted(8)->secondsLeft())->toBe(1);
    });

    it('never counts a sleep twice, since a real sleep passes as well', function () {
        $deadline = deadlineStarted(4);

        $deadline->slept(4);

        expect($deadline->secondsLeft())->toBe(5);
    });
});
