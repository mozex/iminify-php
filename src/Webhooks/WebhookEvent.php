<?php

declare(strict_types=1);

namespace Mozex\Iminify\Webhooks;

use Closure;
use DateTimeImmutable;
use Exception;
use Mozex\Iminify\Enums\WebhookEventType;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\Image;
use Mozex\Iminify\Responses\Scan;
use TypeError;

/**
 * What a webhook sent. `$id` stays the same when a failed send is sent again, so an event you
 * already handled can be skipped.
 */
class WebhookEvent
{
    /**
     * @param  DateTimeImmutable  $createdAt  when the image or the scan finished
     * @param  array<array-key, mixed>  $data  the image or the scan as the API answers it; empty on a ping
     * @param  array<array-key, mixed>  $attributes  the body as it was sent
     */
    public function __construct(
        public readonly string $id,
        public readonly WebhookEventType $type,
        public readonly DateTimeImmutable $createdAt,
        public readonly array $data,
        protected readonly array $attributes,
    ) {}

    /**
     * @throws UnexpectedResponseException when the body doesn't read as an event
     */
    public static function fromPayload(string $payload): self
    {
        return self::read(function () use ($payload): self {
            $attributes = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return new self(
                $attributes['id'] ?? null,
                WebhookEventType::tryFrom($attributes['type'] ?? null) ?? WebhookEventType::Unknown,
                new DateTimeImmutable($attributes['created_at'] ?? null),
                $attributes['data'] ?? null,
                $attributes,
            );
        });
    }

    /**
     * The image an `image.*` event is about, as `GET /images/{id}` answers it.
     */
    public function image(): ?Image
    {
        return in_array($this->type, [WebhookEventType::ImageCompleted, WebhookEventType::ImageFailed, WebhookEventType::ImageResponsiveFinished], true)
            ? self::read(fn (): Image => Image::from($this->data))
            : null;
    }

    /**
     * The scan a `scan.*` event is about, as a list answers it: its counts, with no images or report.
     */
    public function scan(): ?Scan
    {
        return in_array($this->type, [WebhookEventType::ScanCompleted, WebhookEventType::ScanFailed], true)
            ? self::read(fn (): Scan => Scan::from($this->data))
            : null;
    }

    /**
     * The body as it was sent, field names and any field this client doesn't know included.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @template TRecord
     *
     * @param  Closure(): TRecord  $build
     * @return TRecord
     */
    protected static function read(Closure $build): mixed
    {
        try {
            return $build();
        } catch (IminifyException $exception) {
            throw $exception;
        } catch (TypeError|Exception $exception) {
            throw UnexpectedResponseException::unreadable($exception);
        }
    }
}
