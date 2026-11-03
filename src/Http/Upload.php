<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * A file on its way to the API: a path, the bytes or an open stream, under the name the
 * API reads its extension from.
 */
class Upload
{
    /**
     * @param  string|resource|StreamInterface  $source  a path when `$isPath`, otherwise the bytes or a stream
     */
    protected function __construct(
        protected mixed $source,
        public readonly string $name,
        protected bool $isPath = false,
    ) {}

    public static function fromPath(string $path, ?string $name = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("There's no readable file at [{$path}].");
        }

        return new self($path, self::name($name ?? basename($path)), isPath: true);
    }

    public static function fromContents(string $contents, string $name): self
    {
        return new self($contents, self::name($name));
    }

    /**
     * @param  resource|StreamInterface  $stream  read from where it stands now
     */
    public static function fromStream(mixed $stream, string $name): self
    {
        if (! is_resource($stream) && ! $stream instanceof StreamInterface) {
            throw new InvalidArgumentException('A stream upload takes an open stream or a PSR-7 stream.');
        }

        return new self($stream, self::name($name));
    }

    /**
     * Copies the file into an open stream without holding it all in memory.
     *
     * @param  resource  $target
     */
    public function copyTo(mixed $target): void
    {
        if ($this->source instanceof StreamInterface) {
            while (! $this->source->eof()) {
                fwrite($target, $this->source->read(1048576));
            }

            return;
        }

        if (is_resource($this->source)) {
            stream_copy_to_stream($this->source, $target);

            return;
        }

        if (! $this->isPath) {
            fwrite($target, (string) $this->source);

            return;
        }

        $handle = fopen((string) $this->source, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("The file at [{$this->source}] can't be opened.");
        }

        stream_copy_to_stream($handle, $target);
        fclose($handle);
    }

    protected static function name(string $name): string
    {
        return trim($name) !== '' ? $name : throw new InvalidArgumentException('An upload needs a file name, whose extension the API reads.');
    }
}
