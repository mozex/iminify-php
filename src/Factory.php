<?php

declare(strict_types=1);

namespace Mozex\Iminify;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Mozex\Iminify\Http\HttpTransporter;
use Mozex\Iminify\Support\ClientIdentifier;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;

class Factory
{
    protected ?string $apiKey = null;

    protected string $baseUri = Iminify::BASE_URI;

    protected ?ClientInterface $httpClient = null;

    protected ?RequestFactoryInterface $requestFactory = null;

    protected ?StreamFactoryInterface $streamFactory = null;

    protected ?string $clientIdentifier = null;

    /** @var array<string, string> */
    protected array $headers = [];

    protected ?Closure $sleep = null;

    public function withApiKey(#[SensitiveParameter] string $apiKey): static
    {
        $this->apiKey = trim($apiKey);

        return $this;
    }

    /**
     * Leave it out for the live API. A redirect is never followed, so the address has to be
     * the one the API answers at.
     */
    public function withBaseUri(string $baseUri): static
    {
        if (preg_match('#\Ahttps?://[^/\s]+#i', $baseUri) !== 1) {
            throw new InvalidArgumentException("The base URI must be an http or https address, [{$baseUri}] given.");
        }

        $this->baseUri = rtrim($baseUri, '/');

        return $this;
    }

    /**
     * Any PSR-18 client. Leave it out and the one installed is discovered.
     */
    public function withHttpClient(ClientInterface $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    public function withRequestFactory(RequestFactoryInterface $factory): static
    {
        $this->requestFactory = $factory;

        return $this;
    }

    public function withStreamFactory(StreamFactoryInterface $factory): static
    {
        $this->streamFactory = $factory;

        return $this;
    }

    public function withHttpHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * What the `Iminify-Client` header declares, `<name>/<version>`, for a package built on this one.
     */
    public function withClientIdentifier(string $identifier): static
    {
        $this->clientIdentifier = $identifier;

        return $this;
    }

    /**
     * What waiting calls instead of `usleep()`, given the seconds to wait.
     *
     * @param  Closure(float): void  $sleep
     */
    public function withSleep(Closure $sleep): static
    {
        $this->sleep = $sleep;

        return $this;
    }

    public function make(): Client
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new InvalidArgumentException('An Iminify API key is required. Make one under Settings > API Tokens at https://www.iminify.com.');
        }

        return new Client(new HttpTransporter(
            $this->httpClient ?? Psr18ClientDiscovery::find(),
            $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $this->baseUri,
            [
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Iminify-Client' => $this->clientIdentifier ?? ClientIdentifier::for('php', 'mozex/iminify-php'),
            ] + $this->headers,
        ), $this->sleep);
    }
}
