<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\CrawlStatus;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\ProRequiredException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Crawl;
use Mozex\Iminify\Responses\Monitor;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;

const MONITOR_ID = '9d3c4e3b-8d9e-4f0a-b1c2-d3e4f5a6b7c8';

describe('a weekly check', function () {
    it('reads the check and its newest run', function () {
        $monitor = (new FakeClient([Fixtures::monitor()]))->monitors()->get(MONITOR_ID);

        expect($monitor->id)->toBe(MONITOR_ID)
            ->and($monitor->url)->toBe('https://example.com/sitemap.xml')
            ->and($monitor->source)->toBe('sitemap')
            ->and($monitor->crawlId)->toBe('9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6')
            ->and($monitor->settings->level)->toBe('smart')
            ->and($monitor->emails)->toBeTrue()
            ->and($monitor->paused)->toBeFalse()
            ->and($monitor->nextRunAt->format(DATE_ATOM))->toBe('2026-10-15T11:04:55+00:00')
            ->and($monitor->lastRun?->id)->toBe('9d3c6a14-2b3c-4d5e-8f6a-7b8c9d0e1f23')
            ->and($monitor->lastRun?->status)->toBe(CrawlStatus::Completed)
            ->and($monitor->lastRun?->monitorId)->toBe(MONITOR_ID)
            ->and($monitor->lastRun?->pages)->toBeNull()
            ->and($monitor->createdAt->format(DATE_ATOM))->toBe('2026-10-01T11:06:12+00:00')
            ->and($monitor->toArray())->toBe(Fixtures::monitor());
    });

    it('reads a new check, which has no run yet', function () {
        expect((new FakeClient([Fixtures::newMonitor()]))->monitors()->get(MONITOR_ID)->lastRun)->toBeNull();
    });

    it('refuses an answer it can not read', function (array $answer) {
        expect(fn () => (new FakeClient([$answer]))->monitors()->get(MONITOR_ID))->toThrow(UnexpectedResponseException::class);
    })->with([
        'no id' => [array_diff_key(Fixtures::monitor(), ['id' => true])],
        'no next run' => [array_diff_key(Fixtures::monitor(), ['next_run_at' => true])],
        'a run without its id' => [Fixtures::monitor(['last_run' => array_diff_key(Fixtures::monitor()['last_run'], ['id' => true])])],
        'an empty date, which PHP alone reads as now' => [Fixtures::monitor(['created_at' => ''])],
        'a relative date' => [Fixtures::monitor(['next_run_at' => 'tomorrow'])],
    ]);
});

describe('the calls', function () {
    it('lists every check as a plain list, each with the limits of the answer', function () {
        $fake = new FakeClient([Fixtures::answer(['data' => [Fixtures::monitor(['id' => 'a']), Fixtures::newMonitor(['id' => 'b'])]], headers: ['X-RateLimit-Remaining' => '59'])]);

        $monitors = $fake->monitors()->list();

        $fake->assertSent('GET', 'monitors');

        expect($monitors)->toHaveCount(2)
            ->and($monitors[0])->toBeInstanceOf(Monitor::class)
            ->and($monitors[1]->id)->toBe('b')
            ->and($monitors[1]->rateLimit->remaining)->toBe(59);
    });

    it('makes a check from a crawl given by id or by the record', function () {
        $fake = new FakeClient([Fixtures::newMonitor(), Fixtures::monitor()]);

        $fake->monitors()->create('a-crawl');
        $fake->monitors()->create(Crawl::from(Fixtures::queuedCrawl(['id' => 'from-the-record'])));

        expect($fake->requests()[0]->json)->toBe(['crawl_id' => 'a-crawl'])
            ->and($fake->requests()[1]->json)->toBe(['crawl_id' => 'from-the-record']);

        $fake->assertSentTimes('POST', 'monitors', 2);
    });

    it('updates and deletes a check given by id or by the record', function () {
        $monitor = Monitor::from(Fixtures::monitor(['id' => 'from-the-record']));
        $fake = new FakeClient([Fixtures::monitor(['emails' => false]), Fixtures::noContent()]);

        $updated = $fake->monitors()->update($monitor, ['emails' => false]);
        $fake->monitors()->delete('an-id');

        $fake->assertSent('PATCH', 'monitors/from-the-record', fn (ApiRequest $request): bool => $request->json === ['emails' => false]);
        $fake->assertSent('DELETE', 'monitors/an-id');

        expect($updated->emails)->toBeFalse();
    });

    it('reaches its exception on a plan without checks and past the limit', function () {
        $fake = new FakeClient([Fixtures::refusal('pro_required', 403), Fixtures::refusal('monitor_limit_reached', 409)]);

        expect(fn () => $fake->monitors()->create('a'))->toThrow(ProRequiredException::class)
            ->and(fn () => $fake->monitors()->create('b'))->toThrow(ConflictException::class);
    });
});
