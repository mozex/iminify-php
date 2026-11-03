<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use InvalidArgumentException;
use JsonException;
use Mozex\Iminify\Contracts\TransporterContract;
use Mozex\Iminify\Exceptions\ApiException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class HttpTransporter implements TransporterContract
{
    /**
     * @param  array<string, string>  $headers  sent with every request
     */
    public function __construct(
        protected ClientInterface $client,
        protected RequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
        protected string $baseUri,
        protected array $headers,
    ) {}

    public function send(ApiRequest $request): ApiResponse
    {
        try {
            $response = ApiResponse::fromPsr($this->client->sendRequest($this->toPsr($request)));
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException("Couldn't reach Iminify: {$exception->getMessage()}", 0, $exception);
        }

        if ($response->status >= 300 && $response->status < 400) {
            throw UnexpectedResponseException::redirect($response->status, $response->header('location'));
        }

        if ($response->status >= 400) {
            throw ApiException::fromResponse($response);
        }

        return $response;
    }

    /**
     * The key stays out of a var_dump() and of anything that logs one.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUri' => $this->baseUri, 'headers' => ['Authorization' => '[hidden]'] + $this->headers];
    }

    protected function toPsr(ApiRequest $request): RequestInterface
    {
        $uri = "{$this->baseUri}/{$request->path}";

        if ($request->query !== []) {
            $uri .= '?'.http_build_query($request->query, '', '&', PHP_QUERY_RFC3986);
        }

        $psr = $this->requestFactory->createRequest($request->method, $uri);

        foreach ($this->headers as $name => $value) {
            $psr = $psr->withHeader($name, $value);
        }

        if ($request->upload !== null) {
            $boundary = bin2hex(random_bytes(16));

            return $psr
                ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
                ->withBody($this->multipart($request, $request->upload, $boundary));
        }

        if ($request->json !== null) {
            return $psr
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->encode($request->json)));
        }

        return $psr;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function encode(array $json): string
    {
        try {
            return json_encode($json === [] ? (object) [] : $json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("The request can't be sent as JSON: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * The body goes through php://temp, which spills to disk past its memory threshold, so a
     * large image never has to fit in PHP's memory.
     */
    protected function multipart(ApiRequest $request, Upload $upload, string $boundary): StreamInterface
    {
        $body = fopen('php://temp', 'r+b');

        if ($body === false) {
            throw new TransportException("Couldn't open a temporary stream for the upload.");
        }

        foreach ($request->fields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $field = is_array($value) ? "{$name}[]" : $name;
                fwrite($body, "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$this->quote($field)}\"\r\n\r\n{$this->formValue($item)}\r\n");
            }
        }

        fwrite($body, "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$this->quote($upload->name)}\"\r\nContent-Type: application/octet-stream\r\n\r\n");
        $upload->copyTo($body);
        fwrite($body, "\r\n--{$boundary}--\r\n");
        rewind($body);

        return $this->streamFactory->createStreamFromResource($body);
    }

    protected function formValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException('A form field takes a scalar or a list of scalars.'),
        };
    }

    /**
     * A name is written the way browsers write it, so it can't end the header it sits in.
     */
    protected function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }
}
