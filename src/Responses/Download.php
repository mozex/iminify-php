<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Closure;
use ErrorException;
use InvalidArgumentException;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Http\ApiResponse;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A file on its way from the API. Nothing is read until you ask for it, so a large file
 * never has to fit in memory unless you call `contents()`.
 */
class Download
{
    /**
     * @param  string|null  $name  the name the API offers it under, with any folder stripped; it comes from outside your app, so check it before using it in a path of your own
     * @param  int|null  $size  in bytes, when the answer says
     */
    public function __construct(
        public readonly StreamInterface $body,
        public readonly ?string $name,
        public readonly ?string $mimeType,
        public readonly ?int $size,
        public readonly RateLimit $rateLimit,
    ) {}

    public static function fromResponse(ApiResponse $response): self
    {
        return new self(
            $response->body,
            self::fileName($response->header('content-disposition')),
            $response->header('content-type'),
            self::fileSize($response),
            $response->rateLimit(),
        );
    }

    public function contents(): string
    {
        if ($this->body->isSeekable()) {
            $this->body->rewind();
        }

        $contents = '';

        while (! $this->body->eof()) {
            $contents .= $this->read();
        }

        return $contents;
    }

    /**
     * Writes the file to an open stream and returns the bytes written.
     *
     * @param  resource  $stream
     */
    public function writeTo(mixed $stream): int
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Writing a download takes an open stream.');
        }

        if ($this->body->isSeekable()) {
            $this->body->rewind();
        }

        $written = 0;

        while (! $this->body->eof()) {
            $chunk = $this->read();
            $bytes = @fwrite($stream, $chunk);

            if ($bytes === false || $bytes < strlen($chunk)) {
                throw new IminifyException('The download could not be written: the disk may be full.');
            }

            $written += $bytes;
        }

        return $written;
    }

    /**
     * Saves the file and returns its size. It lands in one step, through a temporary file beside
     * the target, so a broken transfer leaves whatever was at the path untouched. A link to a
     * file is followed. A file it replaces keeps its permissions, and belongs from then on to the
     * user PHP runs as, since replacing a file can't keep its owner.
     *
     * @param  int|null  $expectedSize  the bytes it has to come to, such as the `size` the API gave, for an answer that doesn't state its length
     * @param  (Closure(string): void)|null  $check  given the finished temporary file's path before anything is replaced, and throws to keep what's at the path
     */
    public function saveTo(string $path, ?int $expectedSize = null, ?Closure $check = null): int
    {
        if (is_link($path)) {
            $path = realpath($path) ?: $path;
        }

        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory) || (is_file($path) && ! is_writable($path))) {
            throw new IminifyException("The download can't be saved to [{$path}]: it or its folder doesn't exist or isn't writable.");
        }

        $temporary = $directory.'/.iminify-'.bin2hex(random_bytes(8)).'.tmp';

        try {
            $written = $this->writeToFile($temporary, is_file($path) ? (int) fileperms($path) & 0777 : null);
            $expected = $expectedSize ?? $this->size;

            if ($written === 0 || ($expected !== null && $written !== $expected)) {
                throw UnexpectedResponseException::incompleteDownload($written, $expected);
            }

            if ($check !== null) {
                $check($temporary);
            }

            if (! $this->quietly(fn (): bool => rename($temporary, $path))) {
                throw new IminifyException("The download can't be saved to [{$path}]: the system refused to replace it, so something may hold it open or its folder may not allow it.");
            }

            return $written;
        } finally {
            $this->quietly(fn (): bool => is_file($temporary) && unlink($temporary));
        }
    }

    /**
     * A streaming client reads the body only now, long after the call returned, so a connection
     * lost mid-file has to end in this package's exception here. A framework's error handler
     * can turn the client's warning into an exception of its own.
     */
    protected function read(): string
    {
        try {
            return $this->body->read(1048576);
        } catch (RuntimeException|ErrorException $exception) {
            throw new TransportException("The download broke off: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * What the file system refuses (a target another process holds open, on Windows) is this
     * class's answer to give and not a warning's.
     */
    protected function quietly(Closure $operation): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The permissions are set before the first byte, so a private file's replacement isn't left
     * readable by anyone else while it arrives. A flush that fails stops here: the bytes were
     * only ever in memory, and the file they would replace is still safe.
     */
    protected function writeToFile(string $path, ?int $mode): int
    {
        $handle = @fopen($path, 'xb');

        if ($handle === false) {
            throw new IminifyException("The download can't be saved to [{$path}].");
        }

        try {
            if ($mode !== null) {
                chmod($path, $mode);
            }

            $written = $this->writeTo($handle);

            if (stream_get_meta_data($handle)['wrapper_type'] === 'plainfile' && ! @fsync($handle)) {
                throw new IminifyException("The download can't be saved to [{$path}]: the disk didn't keep it.");
            }

            return $written;
        } finally {
            fclose($handle);
        }
    }

    /**
     * The name from a `Content-Disposition` header, the UTF-8 form first, as RFC 6266 asks.
     * A name is only ever a name: any folder in it is stripped.
     */
    protected static function fileName(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $name = match (true) {
            preg_match("/filename\*\s*=\s*UTF-8''([^;\s]+)/i", $header, $match) === 1 => rawurldecode($match[1]),
            preg_match('/filename\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/i', $header, $match) === 1 => stripslashes($match[1]),
            preg_match('/filename\s*=\s*([^;\s]+)/i', $header, $match) === 1 => $match[1],
            default => '',
        };

        $name = basename(str_replace('\\', '/', $name));

        return in_array($name, ['', '.', '..'], true) ? null : $name;
    }

    /**
     * An encoded body's length counts the encoded bytes, so it says nothing about the file.
     */
    protected static function fileSize(ApiResponse $response): ?int
    {
        $length = $response->header('content-length');

        if ($length === null || $response->header('content-encoding') !== null) {
            return null;
        }

        return preg_match('/\A\d+\z/', $length) === 1 ? (int) $length : null;
    }
}
