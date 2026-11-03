<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use ErrorException;
use JsonException;
use Mozex\Iminify\Exceptions\ApiException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\RateLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class ApiResponse
{
    /**
     * @param  array<string, string>  $headers  with lowercase names
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly StreamInterface $body,
    ) {}

    public static function fromPsr(ResponseInterface $response): self
    {
        $headers = [];

        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new self($response->getStatusCode(), $headers, $response->getBody());
    }

    /**
     * Hands the answer back when it's a success, and otherwise ends in the exception it calls for.
     *
     * @throws ApiException when the API refused the request
     * @throws UnexpectedResponseException when the answer is a redirect
     */
    public function successful(): self
    {
        if ($this->status >= 300 && $this->status < 400) {
            throw UnexpectedResponseException::redirect($this->status, $this->header('location'));
        }

        if ($this->status >= 400) {
            throw ApiException::fromResponse($this);
        }

        return $this;
    }

    /**
     * The whole body. A streaming client reads it only now, long after the call returned, so a
     * connection lost mid-answer has to end in this package's exception here.
     *
     * @throws TransportException
     */
    public function contents(): string
    {
        try {
            return (string) $this->body;
        } catch (RuntimeException|ErrorException $exception) {
            throw new TransportException("The answer broke off: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function rateLimit(): RateLimit
    {
        return RateLimit::fromHeaders($this->headers);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function json(): array
    {
        try {
            $body = json_decode($this->contents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw UnexpectedResponseException::notJson($this->status);
        }

        return is_array($body) ? $body : throw UnexpectedResponseException::notJson($this->status);
    }

    /**
     * The `data` key every answer but a download and a page of a list wraps its record in.
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        $data = $this->json()['data'] ?? null;

        return is_array($data) ? $data : throw UnexpectedResponseException::missingData();
    }
}
