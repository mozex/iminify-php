<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use Exception;
use InvalidArgumentException;
use Mozex\Iminify\Exceptions\TransportException;
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
     * The bytes left to send, which an upload group declares before its file is sent. It's
     * null for what can't say, such as a stream that can't seek or one closed since.
     */
    public function size(): ?int
    {
        if ($this->source instanceof StreamInterface) {
            $size = $this->source->isSeekable() ? $this->source->getSize() : null;

            return $size === null ? null : max(0, $size - $this->source->tell());
        }

        if (is_resource($this->source)) {
            return self::remainingInResource($this->source);
        }

        if (! is_string($this->source)) {
            return null;
        }

        if (! $this->isPath) {
            return strlen($this->source);
        }

        clearstatcache(true, $this->source);
        $size = @filesize($this->source);

        return $size === false ? null : $size;
    }

    /**
     * Copies the file into an open stream without holding it all in memory. What it copied has
     * to come to what it measured, so a file that broke off or changed while being read is
     * never sent as a complete one.
     *
     * @param  resource  $target
     */
    public function copyTo(mixed $target): void
    {
        $expected = $this->size();
        $copied = $this->copySource($target);

        if ($expected !== null && $copied !== $expected) {
            throw new TransportException("The file came to {$copied} bytes of the {$expected} it should have, so it wasn't sent: it broke off or changed while being read.");
        }
    }

    /**
     * @param  resource  $target
     */
    protected function copySource(mixed $target): int
    {
        if ($this->source instanceof StreamInterface) {
            return $this->copyStream($this->source, $target);
        }

        if (is_resource($this->source)) {
            return self::copy($this->source, $target);
        }

        if (! is_string($this->source)) {
            throw new InvalidArgumentException('The stream to upload was closed before it could be sent.');
        }

        if (! $this->isPath) {
            self::write($target, $this->source);

            return strlen($this->source);
        }

        $handle = @fopen($this->source, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("The file at [{$this->source}] can't be opened.");
        }

        try {
            return self::copy($handle, $target);
        } finally {
            fclose($handle);
        }
    }

    /**
     * A failed read ends in this package's exception, whatever the stream or a framework's error
     * handler made of it. A failed write already does, with its own message.
     *
     * @param  resource  $target
     */
    protected function copyStream(StreamInterface $source, mixed $target): int
    {
        $copied = 0;

        while (! $source->eof()) {
            try {
                $chunk = $source->read(1048576);
            } catch (Exception $exception) {
                throw new TransportException("The file couldn't be read: {$exception->getMessage()}", 0, $exception);
            }

            self::write($target, $chunk);
            $copied += strlen($chunk);
        }

        return $copied;
    }

    /**
     * A write that comes up short (a full disk) would otherwise send a cut-off image as complete.
     *
     * @param  resource  $target
     */
    public static function write(mixed $target, string $bytes): void
    {
        $written = @fwrite($target, $bytes);

        if ($written === false || $written < strlen($bytes)) {
            throw new TransportException("Couldn't write the upload to a temporary stream: the disk may be full.");
        }
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    protected static function copy(mixed $source, mixed $target): int
    {
        $copied = @stream_copy_to_stream($source, $target);

        if ($copied === false) {
            throw new TransportException("Couldn't write the upload to a temporary stream: the disk may be full.");
        }

        return $copied;
    }

    /**
     * @param  resource  $stream
     */
    protected static function remainingInResource(mixed $stream): ?int
    {
        $position = @ftell($stream);

        if ($position === false || ! stream_get_meta_data($stream)['seekable'] || @fseek($stream, 0, SEEK_END) !== 0) {
            return null;
        }

        $end = ftell($stream);
        fseek($stream, $position);

        return $end === false ? null : max(0, $end - $position);
    }

    protected static function name(string $name): string
    {
        return trim($name) !== '' ? $name : throw new InvalidArgumentException('An upload needs a file name, whose extension the API reads.');
    }
}
