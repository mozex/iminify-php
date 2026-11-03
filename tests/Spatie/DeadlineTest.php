<?php

declare(strict_types=1);

use Mozex\Iminify\Spatie\Deadline;

describe('a deadline', function () {
    it('counts what was slept, for a sleep that returned at once', function () {
        $deadline = new Deadline(10);

        $deadline->slept(4);
        $deadline->slept(4);

        expect($deadline->secondsLeft())->toBe(2);
    });

    it('counts the time that really passed, for work that was no sleep at all', function () {
        $deadline = new class(10) extends Deadline
        {
            public function __construct(int $seconds)
            {
                parent::__construct($seconds);

                $this->started -= 8_000_000_000;
            }
        };

        expect($deadline->secondsLeft())->toBe(1);
    });

    it('never counts a sleep twice, since a real sleep passes as well', function () {
        $deadline = new class(10) extends Deadline
        {
            public function __construct(int $seconds)
            {
                parent::__construct($seconds);

                $this->started -= 4_000_000_000;
            }
        };

        $deadline->slept(4);

        expect($deadline->secondsLeft())->toBe(5);
    });
});
