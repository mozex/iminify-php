<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

/**
 * A stream with room for two bytes, as a full disk has for the last of a file.
 */
class ShortWriteStream
{
    /** @var resource|null */
    public $context;

    protected int $taken = 0;

    /**
     * @return resource
     */
    public static function open(): mixed
    {
        if (! in_array('short-write', stream_get_wrappers(), true)) {
            stream_wrapper_register('short-write', self::class);
        }

        return fopen('short-write://disk', 'wb');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        $taken = min(strlen($data), 2 - $this->taken);
        $this->taken += $taken;

        return $taken;
    }
}
