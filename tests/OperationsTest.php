<?php

declare(strict_types=1);

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
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Responses\Account;
use Mozex\Iminify\Responses\AccountLimits;
use Mozex\Iminify\Responses\AccountUsage;
use Mozex\Iminify\Responses\AltText;
use Mozex\Iminify\Responses\AuditFindings;
use Mozex\Iminify\Responses\AuditImage;
use Mozex\Iminify\Responses\ContentCredentials;
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
        $http = new RecordingHttpClient([documentedAnswer($documented['operation'])]);

        $result = clientOperations()[$operationId](clientAnswering($http));

        $path = str_replace(['{id}', '{width}'], [OPERATION_RECORD, (string) OPERATION_WIDTH], $documented['path']);

        expect($http->requests)->toHaveCount(1)
            ->and($http->requests[0]->getMethod())->toBe($documented['method'])
            ->and($http->requests[0]->getUri()->getPath())->toBe('/api/v1'.$path);

        match (true) {
            $status === 204 => expect($result)->toBeNull(),
            $example === null => expect($result)->toBeInstanceOf(Download::class)
                ->and($result->contents())->toBe('the-file'),
            $result instanceof Page => expect($example['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total'])
                ->and($result->currentPage)->toBe($example['meta']['current_page'])
                ->and($result->lastPage)->toBe($example['meta']['last_page'])
                ->and($result->perPage)->toBe($example['meta']['per_page'])
                ->and($result->total)->toBe($example['meta']['total'])
                ->and($result->items)->toHaveCount(count($example['data'])),
            is_array($result) => expect($result)->toHaveCount(count($example['data'])),
            default => expectReadAs($result, $example['data'], $operationId),
        };

        foreach (is_array($result) ? $result : ($result instanceof Page ? $result->items : []) as $index => $record) {
            expectReadAs($record, $example['data'][$index], "{$operationId}.{$index}");
        }
    })->with(fn (): array => array_keys(documentOperations(apiDocument())));

    it('builds every field the document requires of a body', function (string $operationId) {
        $documented = documentOperations(apiDocument())[$operationId];
        $http = new RecordingHttpClient([documentedAnswer($documented['operation'])]);

        clientOperations()[$operationId](clientAnswering($http));

        $body = json_decode((string) $http->requests[0]->getBody(), true);

        foreach (documentedRequiredFields($documented['operation']) as $field) {
            expect(array_key_exists($field, $body))->toBeTrue("{$operationId} sends no `{$field}`.");
        }
    })->with(fn (): array => array_values(array_diff(
        array_keys(array_filter(documentOperations(apiDocument()), fn (array $documented): bool => documentedRequiredFields($documented['operation']) !== [])),
        // These two hand the client their whole body, so the document's fields are the caller's to send.
        ['createGroup', 'updateMonitor'],
    )));

    it('keeps an id from reaching another address, on every path that takes one', function (string $operationId) {
        $documented = documentOperations(apiDocument())[$operationId];
        $http = new RecordingHttpClient([documentedAnswer($documented['operation'])]);

        clientOperations('../account')[$operationId](clientAnswering($http));

        expect($http->requests[0]->getUri()->getPath())
            ->toBe('/api/v1'.str_replace(['{id}', '{width}'], ['..%2Faccount', (string) OPERATION_WIDTH], $documented['path']));
    })->with(fn (): array => array_keys(array_filter(documentOperations(apiDocument()), fn (array $documented): bool => str_contains($documented['path'], '{id}'))));

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

    it('has a property for every field the document gives a record, null only where the document allows it', function (string $class, array $path) {
        $schema = apiDocument()['components']['schemas'];

        foreach ($path as $key) {
            $schema = $schema[$key];
        }

        expect($schema['properties'] ?? [])->not->toBe([]);

        foreach (array_keys($schema['properties']) as $field) {
            $property = camelCase($field);

            expect(property_exists($class, $property))->toBeTrue("{$class} has no property for `{$field}`.")
                ->and((new ReflectionProperty($class, $property))->getType()?->allowsNull())
                ->toBe(documentAllowsNull($schema, $field), "{$class}::\${$property} and the document's `{$field}` disagree on null.");
        }
    })->with([
        [Image::class, ['Image']],
        [ImageFile::class, ['Image', 'properties', 'original']],
        [AltText::class, ['Image', 'properties', 'alt_text']],
        [ContentCredentials::class, ['Image', 'properties', 'content_credentials']],
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
