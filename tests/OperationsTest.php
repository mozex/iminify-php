<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Mozex\Iminify\Client;
use Mozex\Iminify\Enums\AltTextStatus;
use Mozex\Iminify\Enums\CompressionLevel;
use Mozex\Iminify\Enums\ContentCredentialsStatus;
use Mozex\Iminify\Enums\CrawlStatus;
use Mozex\Iminify\Enums\CropAnchor;
use Mozex\Iminify\Enums\GroupFormat;
use Mozex\Iminify\Enums\GroupStatus;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\OutputFormat;
use Mozex\Iminify\Enums\Plan;
use Mozex\Iminify\Enums\Priority;
use Mozex\Iminify\Enums\ResponsiveStatus;
use Mozex\Iminify\Enums\ScanStatus;
use Mozex\Iminify\Enums\SizeLimitStatus;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Responses\Account;
use Mozex\Iminify\Responses\AccountLimits;
use Mozex\Iminify\Responses\AccountUsage;
use Mozex\Iminify\Responses\AuditFindings;
use Mozex\Iminify\Responses\AuditImage;
use Mozex\Iminify\Responses\Crawl;
use Mozex\Iminify\Responses\CrawlAudit;
use Mozex\Iminify\Responses\CrawlPage;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Group;
use Mozex\Iminify\Responses\GroupOutput;
use Mozex\Iminify\Responses\GroupSettings;
use Mozex\Iminify\Responses\GroupSource;
use Mozex\Iminify\Responses\Image;
use Mozex\Iminify\Responses\ImageFile;
use Mozex\Iminify\Responses\ImageSettings;
use Mozex\Iminify\Responses\LargestPaint;
use Mozex\Iminify\Responses\Monitor;
use Mozex\Iminify\Responses\Page;
use Mozex\Iminify\Responses\ResponsiveSet;
use Mozex\Iminify\Responses\Scan;
use Mozex\Iminify\Responses\ScanAudit;
use Mozex\Iminify\Responses\SkippedImage;
use Mozex\Iminify\Responses\Usage;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;

const OPERATION_RECORD = 'b2f7c1d0-0000-4000-8000-000000000001';
const OPERATION_WIDTH = 480;

/**
 * How the client calls each operation of the API's document.
 *
 * @return array<string, Closure(Client): mixed>
 */
function clientOperations(): array
{
    $id = OPERATION_RECORD;

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

describe('the operation map', function () {
    it('covers every operation in the document, and none it no longer has', function () {
        expect(operationsGap(apiDocument(), clientOperations()))->toBe(['missing' => [], 'stale' => []])
            ->and(count(documentOperations(apiDocument())))->toBeGreaterThan(40);
    });

    it('reports an operation taken out of the map', function () {
        $map = clientOperations();
        unset($map['getScan'], $map['createGroup']);

        expect(operationsGap(apiDocument(), $map))->toBe(['missing' => ['createGroup', 'getScan'], 'stale' => []]);
    });

    it('reports an operation the document no longer has', function () {
        $document = apiDocument();
        unset($document['paths']['/account']);

        expect(operationsGap($document, clientOperations()))->toBe(['missing' => [], 'stale' => ['getAccount']]);
    });

    it('sends each to the document\'s verb and path, and reads the document\'s answer', function (string $operationId) {
        $documented = documentOperations(apiDocument())[$operationId];
        [$status, $example] = documentedSuccess($documented['operation']);
        $http = new RecordingHttpClient([
            $example === null ? new Response($status, [], $status === 204 ? '' : 'the-file') : jsonResponse($example, $status),
        ]);

        $result = clientOperations()[$operationId](clientAnswering($http));

        $path = str_replace(['{id}', '{width}'], [OPERATION_RECORD, (string) OPERATION_WIDTH], $documented['path']);

        expect($http->requests)->toHaveCount(1)
            ->and($http->requests[0]->getMethod())->toBe($documented['method'])
            ->and($http->requests[0]->getUri()->getPath())->toBe('/api/v1'.$path);

        match (true) {
            $status === 204 => expect($result)->toBeNull(),
            $example === null => expect($result)->toBeInstanceOf(Download::class)
                ->and($result->contents())->toBe('the-file'),
            $result instanceof Page => expect($result->toArray())->toBe($example)
                ->and($result->items)->toHaveCount(count($example['data'])),
            is_array($result) => expect(array_map(fn (object $record): array => $record->toArray(), $result))->toBe($example['data']),
            default => expect($result->toArray())->toBe($example['data']),
        };

        foreach ($documented['operation']['requestBody']['content']['application/json']['schema']['required'] ?? [] as $field) {
            expect(json_decode((string) $http->requests[0]->getBody(), true))->toHaveKey($field);
        }
    })->with(fn (): array => array_keys(documentOperations(apiDocument())));

    it('names the address the document serves the API at', function () {
        expect(apiDocument()['servers'][0]['url'])->toBe(Iminify::BASE_URI);
    });
});

describe('what the client reads', function () {
    it('knows every value of each status and setting the document lists', function (string $enum, string $schema) {
        $values = array_map(fn (BackedEnum $case): string|int => $case->value, $enum::cases());
        $documented = apiDocument()['components']['schemas'][$schema]['enum'];

        expect(array_values(array_diff($documented, $values)))->toBe([])
            ->and(array_values(array_diff($values, $documented, ['unknown'])))->toBe([]);
    })->with([
        [ImageStatus::class, 'CompressStatus'],
        [AltTextStatus::class, 'AltTextStatus'],
        [ContentCredentialsStatus::class, 'ContentCredentialsStatus'],
        [ResponsiveStatus::class, 'ImageSetStatus'],
        [SizeLimitStatus::class, 'SizeLimitStatus'],
        [ScanStatus::class, 'ScanStatus'],
        [CrawlStatus::class, 'CrawlStatus'],
        [GroupStatus::class, 'GroupStatus'],
        [GroupFormat::class, 'GroupFormat'],
        [CropAnchor::class, 'CropAnchor'],
        [CompressionLevel::class, 'CompressLevel'],
        [Priority::class, 'CompressPriority'],
        [OutputFormat::class, 'CompressOutputExtension'],
    ]);

    it('knows every plan the document lists', function () {
        $values = array_map(fn (Plan $plan): string => $plan->value, Plan::cases());

        expect(array_values(array_diff(apiDocument()['components']['schemas']['Account']['properties']['plan']['enum'], $values)))->toBe([]);
    });

    it('has a property for every field the document gives a record', function (string $class, array $path) {
        $schema = apiDocument()['components']['schemas'];

        foreach ($path as $key) {
            $schema = $schema[$key];
        }

        expect($schema['properties'] ?? [])->not->toBe([]);

        foreach (array_keys($schema['properties']) as $field) {
            $property = lcfirst(str_replace('_', '', ucwords($field, '_')));

            expect(property_exists($class, $property))->toBeTrue("{$class} has no property for `{$field}`.");
        }
    })->with([
        [Image::class, ['Image']],
        [ImageFile::class, ['Image', 'properties', 'original']],
        [ImageSettings::class, ['Image', 'properties', 'settings']],
        [ResponsiveSet::class, ['Image', 'properties', 'responsive']],
        [Scan::class, ['Scan']],
        [ImageSettings::class, ['Scan', 'properties', 'settings']],
        [SkippedImage::class, ['Scan', 'properties', 'skipped', 'items']],
        [ScanAudit::class, ['ScanAudit']],
        [LargestPaint::class, ['ScanAudit', 'properties', 'largest_paint']],
        [AuditFindings::class, ['ScanAudit', 'properties', 'findings']],
        [AuditImage::class, ['ScanAuditImage']],
        [Crawl::class, ['Crawl']],
        [CrawlPage::class, ['CrawlPage']],
        [CrawlAudit::class, ['CrawlAudit']],
        [AuditFindings::class, ['CrawlAudit', 'properties', 'findings']],
        [Monitor::class, ['Monitor']],
        [Group::class, ['Group']],
        [GroupSource::class, ['Group', 'properties', 'source']],
        [GroupSettings::class, ['Group', 'properties', 'settings']],
        [GroupOutput::class, ['GroupOutput']],
        [Account::class, ['Account']],
        [AccountLimits::class, ['Account', 'properties', 'limits']],
        [AccountUsage::class, ['Account', 'properties', 'usage']],
        [Usage::class, ['Account', 'properties', 'usage', 'properties', 'images']],
    ]);
});
