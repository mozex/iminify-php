<?php

declare(strict_types=1);

namespace Mozex\Iminify;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
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

    protected ?int $timeout = null;

    /**
     * A character a header can't carry is refused here, in words that don't quote the key,
     * or the HTTP client's own refusal would put it in an exception message.
     */
    public function withApiKey(#[SensitiveParameter] string $apiKey): static
    {
        $apiKey = trim($apiKey);

        if ($apiKey !== '' && preg_match('/\A[\x21-\x7E]+\z/', $apiKey) !== 1) {
            throw new InvalidArgumentException('That API key holds characters a key never has. Copy it again from Settings > API Tokens.');
        }

        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Leave it out for the live API. The address has to be the one the API answers at, since
     * the bare domain answers with a redirect, which ends in an exception.
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
     * Any PSR-18 client, set up as you need it: give it a timeout, and don't let it follow
     * redirects, since a POST can arrive as a GET. Leave it out and the one installed is
     * discovered, with redirects off.
     */
    public function withHttpClient(ClientInterface $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * The seconds one request may take, on a client this factory finds. PSR-18 has no timeout of
     * its own and a client found has none set, so without this a stalled read blocks for good.
     * Symfony's client and Guzzle take it. Any other client found is refused rather than left
     * without one: pass it through `withHttpClient()`, which keeps whatever you set on it.
     */
    public function withTimeout(int $seconds): static
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException("A timeout is at least one second, [{$seconds}] given.");
        }

        $this->timeout = $seconds;

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
            $this->httpClient ?? $this->discoverHttpClient(),
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

    /**
     * The key stays out of a var_dump() or print_r() of the factory.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['apiKey' => $this->apiKey === null ? null : '[hidden]', 'baseUri' => $this->baseUri];
    }

    /**
     * Discovery prefers Symfony's client where it's installed, and that one follows redirects
     * unless told not to: a POST to the bare domain would arrive as a GET and read as a success.
     * A Guzzle client can't be changed once made, and never follows one through PSR-18.
     */
    protected function discoverHttpClient(): ClientInterface
    {
        $client = $this->findHttpClient();

        return match (true) {
            method_exists($client, 'withOptions') => $client->withOptions(['max_redirects' => 0] + ($this->timeout === null ? [] : ['timeout' => $this->timeout, 'max_duration' => $this->timeout])),
            $this->timeout === null => $client,
            $client instanceof GuzzleClient => new GuzzleClient(['timeout' => $this->timeout]),
            default => throw new InvalidArgumentException('A timeout can\'t be set on the HTTP client found, '.$client::class.'. Pass one set up with yours through withHttpClient().'),
        };
    }

    protected function findHttpClient(): ClientInterface
    {
        return Psr18ClientDiscovery::find();
    }
}
