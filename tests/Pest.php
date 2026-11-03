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

/**
 * The API's published document, kept as a copy so the tests hold the client to it.
 *
 * @return array<string, mixed>
 */
function apiDocument(): array
{
    static $document = null;

    return $document ??= json_decode((string) file_get_contents(__DIR__.'/Fixtures/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * The example the document gives for one operation's answer.
 *
 * @return array<array-key, mixed>
 */
function apiExample(string $operationId, int $status): array
{
    foreach (apiDocument()['paths'] as $operations) {
        foreach ($operations as $operation) {
            if (($operation['operationId'] ?? null) === $operationId) {
                return $operation['responses'][$status]['content']['application/json']['example'];
            }
        }
    }

    throw new RuntimeException("The document has no operation {$operationId}.");
}
