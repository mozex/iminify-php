<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Mozex\Iminify\Client;
use Mozex\Iminify\Enums\CompressionLevel;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Exceptions\DailyLimitReachedException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\ValidationException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\ApiResponse;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use PHPUnit\Framework\AssertionFailedError;

describe('standing in for the client', function () {
    it('takes the place of a client, and keeps what the code under test sent', function () {
        $optimize = fn (Client $client): string => $client->images()
            ->wait($client->images()->upload(Upload::fromContents('bytes', 'hero.jpg'), ['level' => CompressionLevel::Ultra, 'ephemeral' => true]))
            ->name;

        $fake = new FakeClient([
            Fixtures::queuedImage(),
            Fixtures::queuedImage(['status' => 'optimizing']),
            Fixtures::image(['name' => 'hero.webp']),
        ]);

        expect($optimize($fake))->toBe('hero.webp');

        $fake->assertSentTimes('POST', 'images', 1);
        $fake->assertSentTimes('GET', 'images/*', 2);
        $fake->assertSent('POST', 'images', fn (ApiRequest $request): bool => $request->upload?->name === 'hero.jpg'
            && $request->fields === ['level' => 'ultra', 'ephemeral' => true]);
        $fake->assertNotSent('DELETE', 'images/*');

        expect($fake->requests())->toHaveCount(3);
    });

    it('never really sleeps while waiting', function () {
        $fake = new FakeClient(array_fill(0, 5, Fixtures::queuedImage()));
        $fake->addAnswers([Fixtures::image()]);
        $started = hrtime(true);

        $image = $fake->images()->wait('an-id');

        expect($image->status)->toBe(ImageStatus::Completed)
            ->and((hrtime(true) - $started) / 1e9)->toBeLessThan(1.0);
    });

    it('answers a whole page as it stands, and a download from a file', function () {
        $fake = new FakeClient([
            Fixtures::page([Fixtures::image(['id' => 'a'])], currentPage: 1, lastPage: 3, total: 31),
            Fixtures::file('the-bytes', 'hero.webp'),
            Fixtures::noContent(),
        ]);

        $page = $fake->images()->list();
        $download = $fake->images()->download('a');
        $fake->images()->delete('a');

        expect($page->items[0]->id)->toBe('a')
            ->and($page->nextPage)->toBe(2)
            ->and($page->total)->toBe(31)
            ->and($download->name)->toBe('hero.webp')
            ->and($download->size)->toBe(9)
            ->and($download->contents())->toBe('the-bytes');
    });

    it('throws a queued exception, and says so when nothing is queued', function () {
        $fake = new FakeClient([new TransportException('Lost.')]);

        expect(fn () => $fake->images()->get('a'))->toThrow(TransportException::class, 'Lost.')
            ->and(fn () => $fake->images()->get('b'))->toThrow(LogicException::class, 'No answer is queued for GET images/b');
    });

    it('sends nothing until asked', function () {
        $fake = new FakeClient;

        $fake->assertNothingSent();

        expect($fake->transporter())->toBe($fake)
            ->and($fake->requests())->toBe([]);
    });
});

describe('a refusal', function () {
    /*
     * The same answer through the real transporter and through the fake, so a test
     * that queues a refusal meets what production would throw.
     */
    it('ends the way the real client ends it', function (ApiResponse $answer, int $status, array $headers, string $body, string $class) {
        $real = new RecordingHttpClient([new Response($status, $headers, $body)]);
        $fake = new FakeClient([$answer]);

        $caught = [];

        foreach ([clientAnswering($real), $fake] as $client) {
            try {
                $client->images()->get('a');
            } catch (Throwable $exception) {
                $caught[] = $exception;
            }
        }

        expect($caught)->toHaveCount(2)
            ->and($caught[0])->toBeInstanceOf($class)
            ->and($caught[1]::class)->toBe($caught[0]::class)
            ->and($caught[1]->getMessage())->toBe($caught[0]->getMessage());
    })->with([
        'a spent day' => [
            fn () => Fixtures::refusal('daily_limit_reached', 429, 'Spent.'),
            429,
            ['Content-Type' => 'application/json'],
            '{"message":"Spent.","code":"daily_limit_reached"}',
            DailyLimitReachedException::class,
        ],
        'too many requests' => [
            fn () => Fixtures::refusal('rate_limited', 429, 'Slow down.', retryAfter: 7),
            429,
            ['Content-Type' => 'application/json', 'Retry-After' => '7'],
            '{"message":"Slow down.","code":"rate_limited"}',
            RateLimitedException::class,
        ],
        'a bad field' => [
            fn () => Fixtures::refusal('validation_failed', 422, 'Bad.', ['level' => ['Not a level.']]),
            422,
            ['Content-Type' => 'application/json'],
            '{"message":"Bad.","code":"validation_failed","errors":{"level":["Not a level."]}}',
            ValidationException::class,
        ],
    ]);

    it('carries its details to the exception', function () {
        $fake = new FakeClient([
            Fixtures::refusal('rate_limited', 429, retryAfter: 7),
            Fixtures::refusal('validation_failed', 422, errors: ['level' => ['Not a level.']]),
        ]);

        try {
            $fake->images()->get('a');
            $this->fail('It should have been refused.');
        } catch (RateLimitedException $exception) {
            expect($exception->retryAfter)->toBe(7)
                ->and($exception->errorCode)->toBe('rate_limited');
        }

        try {
            $fake->images()->get('a');
            $this->fail('It should have been refused.');
        } catch (ValidationException $exception) {
            expect($exception->errors)->toBe(['level' => ['Not a level.']]);
        }
    });
});

describe('the assertions', function () {
    it('fail the test when the call does not match', function () {
        $fake = new FakeClient([Fixtures::image(), Fixtures::image()]);
        $fake->images()->get('a');
        $fake->images()->cancel('a');

        expect(fn () => $fake->assertSent('GET', 'scans/*'))->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertSent('POST', 'images/*', fn (): bool => false))->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertNotSent('GET', 'images/a'))->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertSentTimes('GET', 'images/*', 2))->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertNothingSent())->toThrow(AssertionFailedError::class);
    });

    it('read a star as one part of the path', function () {
        $fake = new FakeClient([Fixtures::image()]);
        $fake->images()->cancel('an-id');

        expect($fake->sent('POST', 'images/*/cancel'))->toHaveCount(1)
            ->and($fake->sent('post', '/images/an-id/cancel'))->toHaveCount(1)
            ->and($fake->sent('POST', 'images/*'))->toBe([])
            ->and($fake->sent('GET', 'images/*/cancel'))->toBe([]);
    });
});
