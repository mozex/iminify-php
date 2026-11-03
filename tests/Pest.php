<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mozex\Iminify\Client;
use Mozex\Iminify\Factory;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use Psr\Http\Message\ResponseInterface;

/**
 * A factory wired to a client that answers from a queue, and never really sleeps.
 */
function factoryAnswering(RecordingHttpClient $http): Factory
{
    return Iminify::factory()
        ->withApiKey('test-key')
        ->withHttpClient($http)
        ->withRequestFactory(new HttpFactory)
        ->withStreamFactory(new HttpFactory)
        ->withSleep(fn (float $seconds) => null);
}

function clientAnswering(RecordingHttpClient $http): Client
{
    return factoryAnswering($http)->make();
}

/**
 * @param  array<array-key, mixed>  $body
 * @param  array<string, string>  $headers
 */
function jsonResponse(array $body, int $status = 200, array $headers = []): ResponseInterface
{
    return new Response($status, ['Content-Type' => 'application/json'] + $headers, (string) json_encode($body));
}
