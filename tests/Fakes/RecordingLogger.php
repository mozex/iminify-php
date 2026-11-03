<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

use Psr\Log\AbstractLogger;

class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string}> */
    public array $lines = [];

    /**
     * @param  mixed  $level
     * @param  string|\Stringable  $message
     * @param  array<array-key, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->lines[] = [(string) $level, (string) $message];
    }

    /**
     * @return list<string>
     */
    public function at(string $level): array
    {
        return array_values(array_map(
            fn (array $line): string => $line[1],
            array_filter($this->lines, fn (array $line): bool => $line[0] === $level),
        ));
    }
}
