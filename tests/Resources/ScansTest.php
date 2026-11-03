<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\CompressionLevel;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\OutputFormat;
use Mozex\Iminify\Enums\ScanStatus;
use Mozex\Iminify\Exceptions\ProRequiredException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Scan;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;

const SCAN_ID = '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7';

describe('starting a scan', function () {
    it('sends the address with its options', function () {
        $fake = new FakeClient([Fixtures::queuedScan()]);

        $scan = $fake->scans()->create('https://example.com/pricing', ['level' => CompressionLevel::Smart, 'format' => OutputFormat::Webp, 'alt_text' => false, 'width' => null]);

        $fake->assertSent('POST', 'scans', fn (ApiRequest $request): bool => $request->json === [
            'url' => 'https://example.com/pricing',
            'level' => 'smart',
            'format' => 'webp',
            'alt_text' => false,
        ]);

        expect($scan->status)->toBe(ScanStatus::Queued)
            ->and($scan->isFinished())->toBeFalse()
            ->and($scan->images)->toBeNull()
            ->and($scan->audit)->toBeNull();
    });
});

describe('a finished scan', function () {
    it('reads its counts, what it skipped, its images and its report', function () {
        $scan = (new FakeClient([Fixtures::scan()]))->scans()->get(SCAN_ID);
        $hero = $scan->audit?->images[0];
        $skipped = $scan->audit?->images[2];

        expect($scan->id)->toBe(SCAN_ID)
            ->and($scan->url)->toBe('https://example.com/pricing')
            ->and($scan->crawlId)->toBeNull()
            ->and($scan->status)->toBe(ScanStatus::Completed)
            ->and($scan->isFinished())->toBeTrue()
            ->and($scan->note)->toStartWith('Rendered in a headless browser.')
            ->and($scan->settings->format)->toBe('webp')
            ->and($scan->settings->width)->toBe(1600)
            ->and($scan->settings->sizeLimit)->toBeNull()
            ->and($scan->skipped)->toHaveCount(2)
            ->and($scan->skipped[0]->url)->toBe('https://cdn.example.com/pricing/hero.jpg?v=2')
            ->and($scan->skipped[0]->reason)->toBe('duplicate')
            ->and($scan->skipped[1]->message)->toContain('SVG')
            ->and($scan->imagesCount)->toBe(12)
            ->and($scan->imagesInProgress)->toBe(0)
            ->and($scan->altTextsInProgress)->toBe(0)
            ->and($scan->imagesFinished)->toBe(12)
            ->and($scan->originalSize)->toBe(18406331)
            ->and($scan->optimizedSize)->toBe(2114902)
            ->and($scan->savedBytes)->toBe(16291429)
            ->and($scan->savedPercent)->toBe(88.51)
            ->and($scan->downloadUrl)->toEndWith('/download')
            ->and($scan->images)->toHaveCount(2)
            ->and($scan->images[0]->status)->toBe(ImageStatus::Completed)
            ->and($scan->images[0]->scanId)->toBe(SCAN_ID)
            ->and($scan->audit?->rendered)->toBeTrue()
            ->and($scan->audit?->imagesFound)->toBe(14)
            ->and($scan->audit?->largestPaint?->kind)->toBe('image')
            ->and($scan->audit?->largestPaint?->element)->toBe('img')
            ->and($scan->audit?->largestPaint?->url)->toBe('https://example.com/images/hero-banner.jpg')
            ->and($scan->audit?->findings->oversized)->toBe(1)
            ->and($scan->audit?->findings->withoutDimensions)->toBe(1)
            ->and($scan->audit?->findings->lazyAboveFold)->toBe(0)
            ->and($scan->audit?->findings->eagerBelowFold)->toBe(1)
            ->and($scan->audit?->findings->missingAlt)->toBe(1)
            ->and($scan->audit?->findings->lcpNotPrioritized)->toBe(1)
            ->and($scan->audit?->oversizedBytes)->toBe(180384)
            ->and($scan->audit?->bytes)->toBe(899380)
            ->and($scan->audit?->formats)->toBe(['webp' => 2, 'jpg' => 1, 'svg' => 1])
            ->and($scan->audit?->images)->toHaveCount(4)
            ->and($hero?->url)->toBe('https://example.com/images/hero-banner.jpg')
            ->and($hero?->format)->toBe('webp')
            ->and($hero?->bytes)->toBe(412306)
            ->and($hero?->naturalWidth)->toBe(3200)
            ->and($hero?->naturalHeight)->toBe(1800)
            ->and($hero?->renderedWidth)->toBe(1200)
            ->and($hero?->renderedHeight)->toBe(675)
            ->and($hero?->aboveFold)->toBeTrue()
            ->and($hero?->largestPaint)->toBeTrue()
            ->and($hero?->alt)->toBe('present')
            ->and($hero?->findings)->toBe(['oversized', 'lcp_not_prioritized'])
            ->and($hero?->wastedBytes)->toBe(180384)
            ->and($hero?->outcome)->toBe('compressed')
            ->and($hero?->skipReason)->toBeNull()
            ->and($hero?->imageId)->toBe('9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d')
            ->and($hero?->formatSizes)->toBeNull()
            ->and($hero?->suggestedAlt)->toBeNull()
            ->and($skipped?->aboveFold)->toBeNull()
            ->and($skipped?->alt)->toBeNull()
            ->and($skipped?->outcome)->toBe('skipped')
            ->and($skipped?->skipReason)->toBe('duplicate')
            ->and($scan->shareUrl)->toBeNull()
            ->and($scan->createdAt->format(DATE_ATOM))->toBe('2026-10-01T11:05:00+00:00')
            ->and($scan->updatedAt->format(DATE_ATOM))->toBe('2026-10-01T11:05:41+00:00')
            ->and($scan->toArray())->toBe(Fixtures::scan());
    });

    it('reads the bytes an image would take in each format as a map', function () {
        $audit = Fixtures::scan()['audit'];
        $audit['images'][0]['format_sizes'] = [['format' => 'webp', 'bytes' => 90], ['format' => 'avif', 'bytes' => 60]];
        $scan = (new FakeClient([Fixtures::scan(['audit' => $audit])]))->scans()->get(SCAN_ID);

        expect($scan->audit?->images[0]->formatSizes)->toBe(['webp' => 90, 'avif' => 60]);
    });

    it('reads a page no browser found a largest paint on', function () {
        $audit = ['largest_paint' => null] + Fixtures::scan()['audit'];
        $scan = (new FakeClient([Fixtures::scan(['audit' => $audit])]))->scans()->get(SCAN_ID);

        expect($scan->audit?->largestPaint)->toBeNull();
    });

    it('refuses an answer it can not read', function (array $answer) {
        expect(fn () => (new FakeClient([$answer]))->scans()->get(SCAN_ID))->toThrow(UnexpectedResponseException::class);
    })->with([
        'no id' => [array_diff_key(Fixtures::scan(), ['id' => true])],
        'no counts' => [array_diff_key(Fixtures::scan(), ['images_in_progress' => true])],
        'no settings' => [array_diff_key(Fixtures::scan(), ['settings' => true])],
        'skipped missing' => [array_diff_key(Fixtures::scan(), ['skipped' => true])],
        'an audit without findings' => [Fixtures::scan(['audit' => ['rendered' => true]])],
        'a skipped image without its reason' => [Fixtures::scan(['skipped' => [['url' => 'https://example.com/a.png']]])],
    ]);
});

describe('the other calls', function () {
    it('lists a page of scans with its filter', function () {
        $fake = new FakeClient([Fixtures::page([Fixtures::queuedScan(['id' => 'a'])], currentPage: 2, lastPage: 3, total: 31)]);

        $page = $fake->scans()->list(['status' => ScanStatus::Completed, 'page' => 2]);

        $fake->assertSent('GET', 'scans', fn (ApiRequest $request): bool => $request->query === ['status' => 'completed', 'page' => 2]);

        expect($page->items[0])->toBeInstanceOf(Scan::class)
            ->and($page->items[0]->id)->toBe('a')
            ->and($page->nextPage)->toBe(3);
    });

    it('reaches every address, by id or by the record', function () {
        $scan = Scan::from(Fixtures::queuedScan(['id' => 'from-the-record']));
        $fake = new FakeClient([
            Fixtures::queuedScan(['status' => 'cancelled']),
            Fixtures::queuedScan(),
            Fixtures::queuedScan(['share_url' => 'https://www.iminify.com/shared/abc']),
            Fixtures::noContent(),
            Fixtures::noContent(),
            Fixtures::file('zip', 'scan.zip', 'application/zip'),
            Fixtures::file('csv', 'report.csv', 'text/csv'),
            Fixtures::file('pdf', 'report.pdf', 'application/pdf'),
        ]);

        $scans = $fake->scans();
        $cancelled = $scans->cancel($scan);
        $scans->retry('an-id');
        $shared = $scans->share('an-id');
        $scans->unshare($scan);
        $scans->delete('an-id');
        $zip = $scans->download('an-id');
        $csv = $scans->downloadReportCsv('an-id');
        $pdf = $scans->downloadReportPdf($scan);

        $fake->assertSent('POST', 'scans/from-the-record/cancel');
        $fake->assertSent('POST', 'scans/an-id/retry');
        $fake->assertSent('POST', 'scans/an-id/share');
        $fake->assertSent('DELETE', 'scans/from-the-record/share');
        $fake->assertSent('DELETE', 'scans/an-id');
        $fake->assertSent('GET', 'scans/an-id/download');
        $fake->assertSent('GET', 'scans/an-id/report.csv');
        $fake->assertSent('GET', 'scans/from-the-record/report.pdf');

        expect($cancelled->status)->toBe(ScanStatus::Cancelled)
            ->and($shared->shareUrl)->toEndWith('/shared/abc')
            ->and($zip->contents())->toBe('zip')
            ->and($csv->contents())->toBe('csv')
            ->and($pdf->contents())->toBe('pdf');
    });

    it('reaches its exception when a report needs a paid plan', function () {
        $fake = new FakeClient([Fixtures::refusal('pro_required', 403)]);

        expect(fn () => $fake->scans()->downloadReportCsv('an-id'))->toThrow(ProRequiredException::class);
    });
});

describe('waiting for a scan', function () {
    it('reads until the scanner is through and no image is compressing', function () {
        $fake = new FakeClient([
            Fixtures::queuedScan(),
            Fixtures::queuedScan(['status' => 'scanning']),
            Fixtures::scan(['images_in_progress' => 2]),
            Fixtures::scan(),
        ]);

        expect($fake->scans()->wait(SCAN_ID)->imagesInProgress)->toBe(0);

        $fake->assertSentTimes('GET', 'scans/*', 4);
    });

    it('ends on every status the scanner stops at, once no image is at work', function (string $status, ScanStatus $expected) {
        $fake = new FakeClient([Fixtures::queuedScan(['status' => $status, 'images_in_progress' => 0])]);

        expect($fake->scans()->wait(SCAN_ID)->status)->toBe($expected);

        $fake->assertSentTimes('GET', 'scans/*', 1);
    })->with([
        ['failed', ScanStatus::Failed],
        ['cancelled', ScanStatus::Cancelled],
        ['no-image-found', ScanStatus::NoImageFound],
        'a status this version does not know' => ['archived', ScanStatus::Unknown],
    ]);

    it('runs out of time while an image is still compressing', function () {
        $fake = new FakeClient(array_fill(0, 200, Fixtures::scan(['images_in_progress' => 1])));

        expect(fn () => $fake->scans()->wait(SCAN_ID, timeout: 3))->toThrow(WaitTimeoutException::class, 'The scan');
    });

    it('waits on for what is asked', function () {
        $fake = new FakeClient([Fixtures::scan(['alt_texts_in_progress' => 3]), Fixtures::scan()]);

        $scan = $fake->scans()->wait(SCAN_ID, until: fn (Scan $scan): bool => $scan->altTextsInProgress === 0);

        expect($scan->altTextsInProgress)->toBe(0);

        $fake->assertSentTimes('GET', 'scans/*', 2);
    });
});
