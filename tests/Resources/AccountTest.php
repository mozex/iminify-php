<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\Plan;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\Usage;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;

describe('the account', function () {
    it('reads the plan, what it allows and what is gone', function () {
        $fake = new FakeClient([Fixtures::answer(['data' => Fixtures::account(['plan_ends_at' => '2026-11-10T09:00:00+00:00'])], headers: ['X-RateLimit-Limit' => '60'])]);

        $account = $fake->account()->get();

        $fake->assertSent('GET', 'account');

        expect($account->name)->toBe('Ada Lovelace')
            ->and($account->email)->toBe('ada@example.com')
            ->and($account->plan)->toBe(Plan::Free)
            ->and($account->planEndsAt?->format(DATE_ATOM))->toBe('2026-11-10T09:00:00+00:00')
            ->and($account->limits->imagesPerDay)->toBe(40)
            ->and($account->limits->scansPerDay)->toBe(3)
            ->and($account->limits->altTextsPerDay)->toBe(40)
            ->and($account->limits->filesPerDay)->toBe(1500)
            ->and($account->limits->imagesPerScan)->toBe(20)
            ->and($account->limits->crawlsPerDay)->toBe(0)
            ->and($account->limits->pagesPerCrawl)->toBe(0)
            ->and($account->limits->monitors)->toBe(0)
            ->and($account->limits->maxFileSize)->toBe(33554432)
            ->and($account->limits->maxMegapixels)->toBe(64)
            ->and($account->limits->compressionsPerMinute)->toBe(20)
            ->and($account->limits->scansPerMinute)->toBe(1)
            ->and($account->limits->requestsPerMinute)->toBe(60)
            ->and($account->limits->maxBatch)->toBe(150)
            ->and($account->usage->images->used)->toBe(12)
            ->and($account->usage->images->remaining)->toBe(28)
            ->and($account->usage->images->resetsAt?->format(DATE_ATOM))->toBe('2026-10-02T09:30:00+00:00')
            ->and($account->usage->scans->remaining)->toBe(2)
            ->and($account->usage->crawls->resetsAt)->toBeNull()
            ->and($account->usage->altTexts->used)->toBe(12)
            ->and($account->usage->files->remaining)->toBe(1494)
            ->and($account->rateLimit->limit)->toBe(60)
            ->and($account->toArray()['usage']['files']['used'])->toBe(6);
    });

    it('reads a null daily limit on the unlimited plan as unlimited, never as zero', function () {
        $account = (new FakeClient([Fixtures::maxAccount()]))->account()->get();

        expect($account->plan)->toBe(Plan::Max)
            ->and($account->planEndsAt)->toBeNull()
            ->and($account->limits->imagesPerDay)->toBeNull()
            ->and($account->limits->scansPerDay)->toBeNull()
            ->and($account->limits->filesPerDay)->toBeNull()
            ->and($account->limits->crawlsPerDay)->toBe(10)
            ->and($account->usage->images->remaining)->toBeNull()
            ->and($account->usage->images->isUnlimited())->toBeTrue()
            ->and($account->usage->images->isSpent())->toBeFalse()
            ->and($account->usage->scans->remaining)->toBeNull()
            ->and($account->usage->files->remaining)->toBeNull()
            ->and($account->usage->crawls->remaining)->toBe(10)
            ->and($account->usage->crawls->isUnlimited())->toBeFalse();
    });

    it('tells a spent limit from one with room', function (?int $remaining, bool $spent) {
        expect((new Usage(5, $remaining, null))->isSpent())->toBe($spent);
    })->with([
        'unlimited' => [null, false],
        'spent' => [0, true],
        'room left' => [1, false],
    ]);

    it('reads a plan this version does not know, and refuses an answer it can not read', function () {
        expect((new FakeClient([Fixtures::account(['plan' => 'enterprise'])]))->account()->get()->plan)->toBe(Plan::Unknown)
            ->and(fn () => (new FakeClient([Fixtures::account(['limits' => null])]))->account()->get())->toThrow(UnexpectedResponseException::class)
            ->and(fn () => (new FakeClient([Fixtures::account(['plan_ends_at' => ''])]))->account()->get())->toThrow(UnexpectedResponseException::class)
            ->and(fn () => (new FakeClient([Fixtures::account(['usage' => ['images' => ['resets_at' => 'tomorrow', 'used' => 1, 'remaining' => 1]] + Fixtures::account()['usage']])]))->account()->get())->toThrow(UnexpectedResponseException::class)
            ->and(fn () => (new FakeClient([array_diff_key(Fixtures::account(), ['name' => true])]))->account()->get())->toThrow(UnexpectedResponseException::class);
    });
});
