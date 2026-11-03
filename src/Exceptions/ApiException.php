<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

use JsonException;
use Mozex\Iminify\Http\ApiResponse;

/**
 * The API answered with an error. Branch on `$errorCode`, which stays stable, rather than
 * on the message, which gets reworded. Every code is listed at https://www.iminify.com/docs/errors.
 */
class ApiException extends IminifyException
{
    /**
     * @param  array<array-key, mixed>  $errors  on a validation failure, each field's messages
     * @param  int|null  $retryAfter  the answer's `Retry-After`, in seconds
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly array $errors = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(ApiResponse $response): self
    {
        $body = self::body($response);
        $code = is_string($body['code'] ?? null) ? $body['code'] : 'http_error';
        $message = is_string($body['message'] ?? null) ? $body['message'] : "Iminify answered HTTP {$response->status} with no error details.";
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];

        $class = match (true) {
            $code === 'daily_limit_reached' => DailyLimitReachedException::class,
            $code === 'pro_required' => ProRequiredException::class,
            $response->status === 429 => RateLimitedException::class,
            $response->status === 401 => AuthenticationException::class,
            $response->status === 403 => AccessDeniedException::class,
            $response->status === 404 => NotFoundException::class,
            $response->status === 409 => ConflictException::class,
            $response->status === 413 => PayloadTooLargeException::class,
            $response->status === 422 => ValidationException::class,
            $response->status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $response->status, $code, $errors, $response->rateLimit()->retryAfter);
    }

    /**
     * What a proxy in front of the API answers (a gateway error page) isn't JSON, and
     * must still end in the exception its status calls for.
     *
     * @return array<array-key, mixed>
     */
    protected static function body(ApiResponse $response): array
    {
        try {
            $body = json_decode((string) $response->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($body) ? $body : [];
    }
}
