<?php

declare(strict_types=1);

namespace Mozex\Iminify\Spatie;

use Closure;
use InvalidArgumentException;
use Mozex\Iminify\Client;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Resources\Images;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Image as IminifyImage;
use Psr\Log\LoggerInterface;
use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\Optimizers\BaseSelfHandlingOptimizer;
use Throwable;

/**
 * An optimizer for spatie/image-optimizer's chain, and so for spatie/image and Browsershot,
 * that compresses a file through Iminify and writes the smaller copy back over it. A failure
 * leaves the file as it was and throws, which the chain logs and carries on from by default.
 *
 * Its options are `api_key`, `timeout` (how long it waits for a file, and the limit on each
 * request, 120 seconds by default), `level` and `keep_metadata`. A chain can go on a queue, so
 * it keeps no client in a property and makes one for each file: override `client()` to hand it
 * your own.
 */
class IminifyOptimizer extends BaseSelfHandlingOptimizer
{
    public const DEFAULT_TIMEOUT = 120;

    protected const MAX_TIMEOUT = 86400;

    /**
     * What it takes, by type, under the extension the API knows it by. Each comes back in its own
     * format. HEIC and TIFF never do, and Iminify doesn't read AVIF or SVG, so those stay with
     * whatever else is in the chain.
     */
    protected const FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function canHandle(Image $image): bool
    {
        return array_key_exists($image->mime(), static::FORMATS);
    }

    public function handle(Image $image, LoggerInterface $logger): void
    {
        $mime = $image->mime();
        $format = static::FORMATS[$mime] ?? throw new InvalidArgumentException("Iminify can't optimize a file of type [{$mime}] in place.");
        $path = realpath($image->path()) ?: $image->path();

        if (! is_writable($path) || ! is_writable(dirname($path))) {
            throw new IminifyException("The file at [{$image->path()}] can't be replaced: it or its folder isn't writable.");
        }

        $deadline = new Deadline($this->timeout());
        $before = $this->fingerprint($path);
        $images = $this->client()->images();
        $current = $this->retrying(fn (): IminifyImage => $images->upload(Upload::fromPath($path, "image.{$format}"), $this->uploadOptions()), $deadline);

        try {
            $current = $images->wait($current, max(1, $deadline->secondsLeft()));

            $this->writeBack($images, $current, $path, $mime, $before, $deadline, $logger);
        } catch (WaitTimeoutException $exception) {
            $current = $exception->last instanceof IminifyImage ? $exception->last : $current;

            throw $exception;
        } finally {
            $this->discard($images, $current, $logger);
        }
    }

    /**
     * The key stays out of a var_dump() or print_r() of the optimizer.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['options' => ['api_key' => '[hidden]'] + $this->options, 'imagePath' => $this->imagePath];
    }

    /**
     * Made for each file, and never kept: a chain on a queue is serialized, and a client can't be.
     */
    protected function client(): Client
    {
        $apiKey = $this->options['api_key'] ?? null;

        return Iminify::factory()
            ->withApiKey(is_string($apiKey) ? $apiKey : '')
            ->withTimeout($this->timeout())
            ->make();
    }

    protected function timeout(): int
    {
        $timeout = $this->options['timeout'] ?? static::DEFAULT_TIMEOUT;
        $seconds = is_numeric($timeout) ? (int) $timeout : 0;

        if ($seconds < 1 || $seconds > static::MAX_TIMEOUT) {
            throw new InvalidArgumentException('The optimizer\'s timeout option is a number of seconds, from 1 to '.static::MAX_TIMEOUT.'.');
        }

        return $seconds;
    }

    /**
     * Sends no `format`, which keeps the file's own, and no `size_limit`, which could change it.
     * Alt text stays off, or every file would spend one.
     *
     * @return array<string, mixed>
     */
    protected function uploadOptions(): array
    {
        return [
            'level' => $this->options['level'] ?? null,
            'keep_metadata' => $this->options['keep_metadata'] ?? null,
            'alt_text' => false,
            'ephemeral' => true,
        ];
    }

    /**
     * Waits out a short `Retry-After` while there's time for it. A spent daily allowance can last
     * hours and isn't a `RateLimitedException`, so it fails at once.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $call
     * @return TResult
     */
    protected function retrying(Closure $call, Deadline $deadline): mixed
    {
        while (true) {
            try {
                return $call();
            } catch (RateLimitedException $exception) {
                $wait = max(1, $exception->retryAfter ?? 5);

                if ($wait >= $deadline->secondsLeft()) {
                    throw $exception;
                }

                $this->pause($wait);
                $deadline->slept($wait);
            }
        }
    }

    /**
     * The last check runs on the finished download, right before it replaces the file: a client
     * that streams reads the body only then, so the file can change until the end of the transfer.
     *
     * @param  array{int, string}  $before
     */
    protected function writeBack(Images $images, IminifyImage $image, string $path, string $mime, array $before, Deadline $deadline, LoggerInterface $logger): void
    {
        if ($image->status === ImageStatus::AlreadyOptimized) {
            $logger->info('Iminify found nothing to save in this file.');

            return;
        }

        if ($image->status !== ImageStatus::Completed || $image->optimized === null) {
            throw new IminifyException("Iminify couldn't optimize the file: the image ended as [{$image->status->value}].");
        }

        [$size] = $before;
        $format = static::FORMATS[$mime];

        if ($image->original->size !== $size) {
            throw new IminifyException("Iminify received {$image->original->size} bytes of a {$size} byte file, so the file was left as it is.");
        }

        if (static::normalize($image->optimized->format) !== $format) {
            throw new IminifyException("Iminify answered in [{$image->optimized->format}] rather than [{$format}], so the file was left as it is.");
        }

        if ($image->optimized->size >= $size) {
            $logger->info('Iminify\'s copy of this file isn\'t smaller, so the file was left as it is.');

            return;
        }

        $this->retrying(fn (): Download => $images->download($image), $deadline)
            ->saveTo($path, $image->optimized->size, function (string $temporary) use ($path, $mime, $before): void {
                if (mime_content_type($temporary) !== $mime) {
                    throw new IminifyException("What Iminify sent isn't a file of type [{$mime}], so the file was left as it is.");
                }

                if ($this->fingerprint($path) !== $before) {
                    throw new IminifyException('The file changed while Iminify was working on it, so it was left as it is.');
                }
            });
    }

    /**
     * Cleaning up never replaces what happened to the file. An image that won't go now, such as
     * one still optimizing, is deleted by Iminify a while after it finishes, since it was sent
     * as `ephemeral`.
     */
    protected function discard(Images $images, IminifyImage $image, LoggerInterface $logger): void
    {
        try {
            if ($image->status === ImageStatus::Queued) {
                $this->cancel($images, $image);
            }

            $images->delete($image);
        } catch (Throwable $exception) {
            $logger->warning("Iminify image [{$image->id}] stays until Iminify deletes it: {$exception->getMessage()}");
        }
    }

    /**
     * It may have started since it was last read, and then can't be cancelled.
     */
    protected function cancel(Images $images, IminifyImage $image): void
    {
        try {
            $images->cancel($image);
        } catch (ConflictException) {
        }
    }

    /**
     * The size and a hash of the bytes, since a modified time counts in whole seconds.
     *
     * @return array{int, string}
     */
    protected function fingerprint(string $path): array
    {
        clearstatcache(true, $path);

        return [(int) filesize($path), (string) hash_file('xxh128', $path)];
    }

    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }

    protected static function normalize(string $format): string
    {
        $format = strtolower($format);

        return $format === 'jpeg' ? 'jpg' : $format;
    }
}
