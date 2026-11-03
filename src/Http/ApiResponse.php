<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use JsonException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\RateLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
            $body = json_decode((string) $this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw UnexpectedResponseException::notJson($this->status);
        }

        return is_array($body) ? $body : throw UnexpectedResponseException::notJson($this->status);
    }

    /**
     * The `data` key every answer but a download and a list wraps its record in.
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        $data = $this->json()['data'] ?? null;

        return is_array($data) ? $data : throw UnexpectedResponseException::missingData();
    }
}
