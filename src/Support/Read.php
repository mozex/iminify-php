<?php

declare(strict_types=1);

namespace Mozex\Iminify\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reads the values of an answer that PHP on its own would read too kindly, so an answer
 * that's wrong ends in an exception instead of reading as something real.
 */
class Read
{
    /**
     * A date as the API writes it. `DateTimeImmutable` alone reads an empty string as now and
     * "tomorrow" as tomorrow.
     */
    public static function date(string $value): DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T/', $value) !== 1) {
            throw new InvalidArgumentException("[{$value}] isn't a date as the API writes one.");
        }

        return new DateTimeImmutable($value);
    }

    /**
     * A list of rows, each with a `format` and a number, as a map from one to the other.
     * `array_column()` would drop a row that's wrong without a word.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<string, int>
     */
    public static function byFormat(array $rows, string $number): array
    {
        return array_combine(
            array_map(fn (array $row): string => $row['format'] ?? null, $rows),
            array_map(fn (array $row): int => $row[$number] ?? null, $rows),
        );
    }
}
