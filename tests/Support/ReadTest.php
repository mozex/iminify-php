<?php

declare(strict_types=1);

use Mozex\Iminify\Support\Read;

describe('reading a date', function () {
    it('reads each way the API writes one', function (string $value, string $expected) {
        expect(Read::date($value)->format('Y-m-d H:i:s.v P'))->toBe($expected);
    })->with([
        'an offset' => ['2026-10-25T10:42:07+03:00', '2026-10-25 10:42:07.000 +03:00'],
        'Z' => ['2026-10-25T10:42:07Z', '2026-10-25 10:42:07.000 +00:00'],
        'a fraction' => ['2026-10-25T10:42:07.250+00:00', '2026-10-25 10:42:07.250 +00:00'],
    ]);

    it('refuses what PHP alone would read as something real', function (string $value) {
        Read::date($value);
    })->throws(InvalidArgumentException::class)->with([
        'nothing' => [''],
        'a word' => ['tomorrow'],
        'no zone' => ['2026-10-01T09:30:00'],
        'more after it' => ['2026-10-01T09:30:00+00:00 +1 year'],
        'a line after it' => ["2026-10-01T09:30:00+00:00\n"],
    ]);
});
