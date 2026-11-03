<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use InvalidArgumentException;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Http\ApiResponse;
use Psr\Http\Message\StreamInterface;

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
        $length = $response->header('content-length');

        return new self(
            $response->body,
            self::fileName($response->header('content-disposition')),
            $response->header('content-type'),
            $response->header('content-encoding') === null && $length !== null && ctype_digit($length) ? (int) $length : null,
            $response->rateLimit(),
        );
    }

    public function contents(): string
    {
        return (string) $this->body;
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
            $chunk = $this->body->read(1048576);
            $bytes = fwrite($stream, $chunk);

            if ($bytes === false || $bytes < strlen($chunk)) {
                throw new IminifyException('The download could not be written: the disk may be full.');
            }

            $written += $bytes;
        }

        return $written;
    }

    /**
     * Saves the file and returns its size. It lands in one step, through a temporary file beside
     * the target, so a broken transfer leaves whatever was at the path untouched.
     */
    public function saveTo(string $path): int
    {
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new IminifyException("The download can't be saved to [{$path}]: its folder doesn't exist or isn't writable.");
        }

        $temporary = $directory.'/.iminify-'.bin2hex(random_bytes(8)).'.tmp';

        try {
            $written = $this->writeToFile($temporary);

            if ($written === 0 || ($this->size !== null && $written !== $this->size)) {
                throw new UnexpectedResponseException("The download ended after {$written} bytes".($this->size === null ? '.' : " of {$this->size}."));
            }

            $mode = is_file($path) ? fileperms($path) : false;

            if (! @rename($temporary, $path)) {
                throw new IminifyException("The download can't be saved to [{$path}]: something may hold the file open.");
            }

            if ($mode !== false) {
                chmod($path, $mode & 0777);
            }

            return $written;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    protected function writeToFile(string $path): int
    {
        $handle = fopen($path, 'xb');

        if ($handle === false) {
            throw new IminifyException("The download can't be saved to [{$path}].");
        }

        try {
            return $this->writeTo($handle);
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
}
