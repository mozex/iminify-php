<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Mozex\Iminify\Client;
use Mozex\Iminify\Factory;
use Mozex\Iminify\Http\ApiResponse;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use Mozex\Iminify\Webhooks\WebhookVerifier;
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

/**
 * The form fields of a multipart body, by name.
 *
 * @return array<string, list<string>>
 */
function formFields(string $body): array
{
    preg_match_all('/name="([^"]+)"\r\n\r\n(.*?)\r\n/s', $body, $matches, PREG_SET_ORDER);
    $fields = [];

    foreach ($matches as [, $name, $value]) {
        $fields[$name][] = $value;
    }

    return $fields;
}

/**
 * @return array{0: Closure(float): void, 1: ArrayObject<int, float>}
 */
function recordingSleep(): array
{
    $slept = new ArrayObject;

    return [function (float $seconds) use ($slept): void {
        $slept->append($seconds);
    }, $slept];
}

/**
 * @param  array<string, string>  $headers
 */
function downloadOf(string $bytes, array $headers = []): Download
{
    return Download::fromResponse(new ApiResponse(200, $headers, Utils::streamFor($bytes)));
}

function downloadFolder(): string
{
    $folder = sys_get_temp_dir().'/iminify-download-'.bin2hex(random_bytes(6));
    mkdir($folder);

    return $folder;
}

function removeFolder(string $folder): void
{
    foreach (glob($folder.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($folder);
}

const OPERATION_RECORD = 'b2f7c1d0-0000-4000-8000-000000000001';
const OPERATION_WIDTH = 480;
const WEBHOOK_SECRET = 'whsec_TfB2r8vQ';
const WEBHOOK_TIME = 1761400000;
const WEBHOOK_BODY = '{"id":"9f1c","type":"ping","created_at":"2026-10-25T10:42:07+00:00","data":[]}';

/**
 * How the client calls each operation of the API's document.
 *
 * @return array<string, Closure(Client): mixed>
 */
function clientOperations(string $id = OPERATION_RECORD): array
{
    return [
        'listImages' => fn (Client $client) => $client->images()->list(),
        'createImage' => fn (Client $client) => $client->images()->fromUrl('https://example.com/a.jpg'),
        'getImage' => fn (Client $client) => $client->images()->get($id),
        'renameImage' => fn (Client $client) => $client->images()->rename($id, 'a'),
        'deleteImage' => fn (Client $client) => $client->images()->delete($id),
        'downloadImage' => fn (Client $client) => $client->images()->download($id),
        'downloadResponsiveImage' => fn (Client $client) => $client->images()->downloadResponsive($id, OPERATION_WIDTH),
        'downloadResponsiveImages' => fn (Client $client) => $client->images()->downloadResponsiveZip($id),
        'recompressImage' => fn (Client $client) => $client->images()->recompress($id),
        'retryImage' => fn (Client $client) => $client->images()->retry($id),
        'cancelImage' => fn (Client $client) => $client->images()->cancel($id),
        'writeAltText' => fn (Client $client) => $client->images()->writeAltText($id),
        'shareImage' => fn (Client $client) => $client->images()->share($id),
        'unshareImage' => fn (Client $client) => $client->images()->unshare($id),
        'renameImages' => fn (Client $client) => $client->images()->renameMany([$id], 'a'),
        'downloadImages' => fn (Client $client) => $client->images()->downloadMany([$id]),

        'listScans' => fn (Client $client) => $client->scans()->list(),
        'createScan' => fn (Client $client) => $client->scans()->create('https://example.com/'),
        'getScan' => fn (Client $client) => $client->scans()->get($id),
        'deleteScan' => fn (Client $client) => $client->scans()->delete($id),
        'retryScan' => fn (Client $client) => $client->scans()->retry($id),
        'cancelScan' => fn (Client $client) => $client->scans()->cancel($id),
        'downloadScan' => fn (Client $client) => $client->scans()->download($id),
        'shareScanReport' => fn (Client $client) => $client->scans()->share($id),
        'unshareScanReport' => fn (Client $client) => $client->scans()->unshare($id),
        'downloadScanReportCsv' => fn (Client $client) => $client->scans()->downloadReportCsv($id),
        'downloadScanReportPdf' => fn (Client $client) => $client->scans()->downloadReportPdf($id),

        'createGroup' => fn (Client $client) => $client->groups()->create([
            'name' => 'a',
            'source' => ['format' => 'jpg', 'size' => 1, 'width' => 1, 'height' => 1],
            'outputs' => [['name' => 'a', 'width' => 1, 'height' => 1]],
        ]),
        'sendGroupSource' => fn (Client $client) => $client->groups()->sendSource($id, Upload::fromContents('a', 'a.jpg')),
        'getGroup' => fn (Client $client) => $client->groups()->get($id),
        'deleteGroup' => fn (Client $client) => $client->groups()->delete($id),
        'downloadGroup' => fn (Client $client) => $client->groups()->download($id),

        'listCrawls' => fn (Client $client) => $client->crawls()->list(),
        'createCrawl' => fn (Client $client) => $client->crawls()->fromSitemap('https://example.com/sitemap.xml'),
        'getCrawl' => fn (Client $client) => $client->crawls()->get($id),
        'deleteCrawl' => fn (Client $client) => $client->crawls()->delete($id),
        'downloadCrawl' => fn (Client $client) => $client->crawls()->download($id),

        'listMonitors' => fn (Client $client) => $client->monitors()->list(),
        'createMonitor' => fn (Client $client) => $client->monitors()->create($id),
        'getMonitor' => fn (Client $client) => $client->monitors()->get($id),
        'updateMonitor' => fn (Client $client) => $client->monitors()->update($id, ['emails' => false]),
        'deleteMonitor' => fn (Client $client) => $client->monitors()->delete($id),

        'getAccount' => fn (Client $client) => $client->account()->get(),
    ];
}

/**
 * Every operation the document names, each with its verb and path.
 *
 * @param  array<string, mixed>  $document
 * @return array<string, array{method: string, path: string, operation: array<string, mixed>}>
 */
function documentOperations(array $document): array
{
    $operations = [];

    foreach ($document['paths'] as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (isset($operation['operationId'])) {
                $operations[$operation['operationId']] = ['method' => strtoupper($method), 'path' => $path, 'operation' => $operation];
            }
        }
    }

    return $operations;
}

/**
 * What the map and the document disagree on, each list sorted.
 *
 * @param  array<string, mixed>  $document
 * @param  array<string, mixed>  $map
 * @return array{missing: list<string>, stale: list<string>}
 */
function operationsGap(array $document, array $map): array
{
    $documented = array_keys(documentOperations($document));
    $mapped = array_keys($map);

    $missing = array_values(array_diff($documented, $mapped));
    $stale = array_values(array_diff($mapped, $documented));
    sort($missing);
    sort($stale);

    return ['missing' => $missing, 'stale' => $stale];
}

/**
 * The first success the document gives an operation: its status and, for a JSON answer, its example.
 *
 * @param  array<string, mixed>  $operation
 * @return array{0: int, 1: array<array-key, mixed>|null}
 */
function documentedSuccess(array $operation): array
{
    foreach ($operation['responses'] as $status => $response) {
        if ($status < 300) {
            return [$status, $response['content']['application/json']['example'] ?? null];
        }
    }

    throw new RuntimeException('The operation has no success.');
}

/**
 * That answer, as the API would send it.
 *
 * @param  array<string, mixed>  $operation
 */
function documentedAnswer(array $operation): ResponseInterface
{
    [$status, $example] = documentedSuccess($operation);

    return $example === null ? new Response($status, [], $status === 204 ? '' : 'the-file') : jsonResponse($example, $status);
}

/**
 * The fields a JSON body has to carry, by the document.
 *
 * @param  array<string, mixed>  $operation
 * @return list<string>
 */
function documentedRequiredFields(array $operation): array
{
    $schema = $operation['requestBody']['content']['application/json']['schema'] ?? [];

    if (isset($schema['$ref'])) {
        $schema = apiDocument()['components']['schemas'][basename($schema['$ref'])];
    }

    return $schema['required'] ?? [];
}

/**
 * Whether the document lets a field of an object be null or be left out.
 *
 * @param  array<string, mixed>  $object
 */
function documentAllowsNull(array $object, string $field): bool
{
    if (! in_array($field, $object['required'] ?? [], true)) {
        return true;
    }

    $property = $object['properties'][$field];
    $types = array_merge((array) ($property['type'] ?? []), array_column($property['anyOf'] ?? [], 'type'));

    return in_array('null', $types, true);
}

function camelCase(string $field): string
{
    return lcfirst(str_replace('_', '', ucwords($field, '_')));
}

/**
 * Holds what a response read, at every depth, to the answer it read it from, so a field read
 * under another's name fails here rather than reading as null or as its neighbour.
 */
function expectReadAs(mixed $read, mixed $sent, string $path): void
{
    match (true) {
        $sent === null => expect($read)->toBeNull($path),
        $read instanceof DateTimeImmutable => expect($read->format(DATE_ATOM))->toBe($sent, $path),
        $read instanceof BackedEnum => expect($read->value)->toBe($sent, $path),
        is_object($read) => array_walk($sent, fn (mixed $value, string $field) => expectReadAs($read->{camelCase($field)}, $value, "{$path}.{$field}")),
        is_array($read) && is_object($read[0] ?? null) => array_walk($sent, fn (mixed $value, int $index) => expectReadAs($read[$index], $value, "{$path}.{$index}")),
        is_float($read) => expect($read)->toBe((float) $sent, $path),
        is_array($read) && ! array_is_list($read) => expect($read)->toHaveCount(count($sent), $path),
        default => expect($read)->toBe($sent, $path),
    };
}

function signedBody(string $body = WEBHOOK_BODY, int $time = WEBHOOK_TIME): string
{
    return (new WebhookVerifier(WEBHOOK_SECRET))->sign($body, $time);
}
