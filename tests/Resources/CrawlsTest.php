<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\CrawlStatus;
use Mozex\Iminify\Enums\OutputFormat;
use Mozex\Iminify\Enums\ScanStatus;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\ProRequiredException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Crawl;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;

const CRAWL_ID = '9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6';

describe('starting a crawl', function () {
    it('sends a sitemap or a list of pages, with its options', function () {
        $fake = new FakeClient([Fixtures::queuedCrawl(), Fixtures::queuedCrawl(['source' => 'pages'])]);

        $fromSitemap = $fake->crawls()->fromSitemap('https://example.com/sitemap.xml', ['format' => OutputFormat::Webp, 'level' => null]);
        $fromPages = $fake->crawls()->fromPages(['https://example.com/', 'https://example.com/pricing'], ['alt_text' => false]);

        expect($fake->requests()[0]->json)->toBe(['sitemap' => 'https://example.com/sitemap.xml', 'format' => 'webp'])
            ->and($fake->requests()[1]->json)->toBe(['pages' => ['https://example.com/', 'https://example.com/pricing'], 'alt_text' => false])
            ->and($fromSitemap->status)->toBe(CrawlStatus::Queued)
            ->and($fromSitemap->isFinished())->toBeFalse()
            ->and($fromSitemap->pages)->toBeNull()
            ->and($fromSitemap->audit)->toBeNull()
            ->and($fromPages->source)->toBe('pages');

        $fake->assertSentTimes('POST', 'crawls', 2);
    });

    it('reaches its exception on a plan without crawls', function () {
        $fake = new FakeClient([Fixtures::refusal('pro_required', 403)]);

        expect(fn () => $fake->crawls()->fromSitemap('https://example.com/sitemap.xml'))->toThrow(ProRequiredException::class);
    });
});

describe('a finished crawl', function () {
    it('reads its counts, its pages and the site\'s report', function () {
        $crawl = (new FakeClient([Fixtures::crawl()]))->crawls()->get(CRAWL_ID);

        expect($crawl->id)->toBe(CRAWL_ID)
            ->and($crawl->url)->toBe('https://example.com/sitemap.xml')
            ->and($crawl->source)->toBe('sitemap')
            ->and($crawl->monitorId)->toBeNull()
            ->and($crawl->status)->toBe(CrawlStatus::Completed)
            ->and($crawl->isFinished())->toBeTrue()
            ->and($crawl->note)->toBeNull()
            ->and((new FakeClient([Fixtures::crawl(['note' => 'The sitemap could not be read.'])]))->crawls()->get(CRAWL_ID)->note)->toBe('The sitemap could not be read.')
            ->and($crawl->settings->format)->toBe('webp')
            ->and($crawl->pagesCount)->toBe(2)
            ->and($crawl->pagesInProgress)->toBe(0)
            ->and($crawl->pagesFailed)->toBe(0)
            ->and($crawl->pagesLeftOut)->toBe(0)
            ->and($crawl->imagesCount)->toBe(12)
            ->and($crawl->imagesInProgress)->toBe(0)
            ->and($crawl->imagesFinished)->toBe(12)
            ->and($crawl->originalSize)->toBe(18406331)
            ->and($crawl->optimizedSize)->toBe(2114902)
            ->and($crawl->savedBytes)->toBe(16291429)
            ->and($crawl->savedPercent)->toBe(88.51)
            ->and($crawl->downloadUrl)->toEndWith('/download')
            ->and($crawl->pages)->toHaveCount(2)
            ->and($crawl->pages[1]->id)->toBe('9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7')
            ->and($crawl->pages[1]->url)->toBe('https://example.com/pricing')
            ->and($crawl->pages[1]->status)->toBe(ScanStatus::Completed)
            ->and($crawl->pages[1]->note)->toStartWith('Rendered')
            ->and($crawl->pages[1]->imagesCount)->toBe(12)
            ->and($crawl->pages[1]->imagesInProgress)->toBe(0)
            ->and($crawl->pages[1]->imagesToFix)->toBe(2)
            ->and($crawl->audit?->pagesAudited)->toBe(2)
            ->and($crawl->audit?->imagesFound)->toBe(4)
            ->and($crawl->audit?->imagesToFix)->toBe(2)
            ->and($crawl->audit?->findings->lcpNotPrioritized)->toBe(1)
            ->and($crawl->audit?->oversizedBytes)->toBe(180384)
            ->and($crawl->audit?->bytes)->toBe(899380)
            ->and($crawl->createdAt->format(DATE_ATOM))->toBe('2026-10-01T11:04:50+00:00')
            ->and($crawl->updatedAt->format(DATE_ATOM))->toBe('2026-10-01T11:04:55+00:00')
            ->and($crawl->toArray())->toBe(Fixtures::crawl());
    });

    it('refuses an answer it can not read', function (array $answer) {
        expect(fn () => (new FakeClient([$answer]))->crawls()->get(CRAWL_ID))->toThrow(UnexpectedResponseException::class);
    })->with([
        'no id' => [array_diff_key(Fixtures::crawl(), ['id' => true])],
        'no counts' => [array_diff_key(Fixtures::crawl(), ['pages_in_progress' => true])],
        'a page without its address' => [Fixtures::crawl(['pages' => [array_diff_key(Fixtures::crawl()['pages'][0], ['url' => true])]])],
        'an audit without findings' => [Fixtures::crawl(['audit' => array_diff_key(Fixtures::crawl()['audit'], ['findings' => true])])],
        'an empty date, which PHP alone reads as now' => [Fixtures::crawl(['created_at' => ''])],
        'a relative date' => [Fixtures::crawl(['updated_at' => 'tomorrow'])],
    ]);
});

describe('the other calls', function () {
    it('lists a page of crawls', function () {
        $fake = new FakeClient([Fixtures::page([Fixtures::queuedCrawl(['id' => 'a'])], lastPage: 2, total: 16)]);

        $page = $fake->crawls()->list(['per_page' => 1]);

        $fake->assertSent('GET', 'crawls', fn (ApiRequest $request): bool => $request->query === ['per_page' => 1]);

        expect($page->items[0])->toBeInstanceOf(Crawl::class)
            ->and($page->nextPage)->toBe(2);
    });

    it('reaches every address, by id or by the record', function () {
        $crawl = Crawl::from(Fixtures::queuedCrawl(['id' => 'from-the-record']));
        $fake = new FakeClient([Fixtures::file('zip', 'crawl.zip', 'application/zip'), Fixtures::noContent(), Fixtures::refusal('scan_in_progress', 409)]);

        expect($fake->crawls()->download($crawl)->contents())->toBe('zip');

        $fake->crawls()->delete('an-id');

        expect(fn () => $fake->crawls()->delete($crawl))->toThrow(ConflictException::class);

        $fake->assertSent('GET', 'crawls/from-the-record/download');
        $fake->assertSent('DELETE', 'crawls/an-id');
        $fake->assertSent('DELETE', 'crawls/from-the-record');
    });
});

describe('waiting for a crawl', function () {
    it('reads until it has stopped and no page or image is at work', function () {
        $fake = new FakeClient([
            Fixtures::queuedCrawl(),
            Fixtures::queuedCrawl(['status' => 'reading']),
            Fixtures::queuedCrawl(['status' => 'scanning']),
            Fixtures::crawl(['pages_in_progress' => 1]),
            Fixtures::crawl(['images_in_progress' => 3]),
            Fixtures::crawl(),
        ]);

        expect($fake->crawls()->wait(CRAWL_ID)->imagesInProgress)->toBe(0);

        $fake->assertSentTimes('GET', 'crawls/*', 6);
    });

    it('ends on a failed crawl and on a status this version does not know', function (string $status, CrawlStatus $expected) {
        $fake = new FakeClient([Fixtures::queuedCrawl(['status' => $status, 'pages_in_progress' => 0, 'images_in_progress' => 0])]);

        expect($fake->crawls()->wait(CRAWL_ID)->status)->toBe($expected);

        $fake->assertSentTimes('GET', 'crawls/*', 1);
    })->with([
        ['failed', CrawlStatus::Failed],
        ['paused', CrawlStatus::Unknown],
    ]);

    it('runs out of time while a page is at work', function () {
        $fake = new FakeClient(array_fill(0, 200, Fixtures::crawl(['pages_in_progress' => 1])));

        expect(fn () => $fake->crawls()->wait(CRAWL_ID, timeout: 3))->toThrow(WaitTimeoutException::class, 'The crawl');
    });

    it('ends on a crawl that failed, whatever is asked', function () {
        $fake = new FakeClient([Fixtures::queuedCrawl(['status' => 'failed', 'pages_in_progress' => 0, 'images_in_progress' => 0])]);

        $crawl = $fake->crawls()->wait(CRAWL_ID, until: fn (Crawl $crawl): bool => $crawl->audit !== null);

        expect($crawl->status)->toBe(CrawlStatus::Failed);

        $fake->assertSentTimes('GET', 'crawls/*', 1);
    });

    it('waits on for what is asked', function () {
        $fake = new FakeClient([Fixtures::crawl(['audit' => null]), Fixtures::crawl()]);

        expect($fake->crawls()->wait(CRAWL_ID, until: fn (Crawl $crawl): bool => $crawl->audit !== null)->audit)->not->toBeNull();

        $fake->assertSentTimes('GET', 'crawls/*', 2);
    });
});
