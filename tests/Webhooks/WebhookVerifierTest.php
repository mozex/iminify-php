<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\ScanStatus;
use Mozex\Iminify\Enums\WebhookEventType;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\InvalidWebhookSignatureException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Testing\Fixtures;
use Mozex\Iminify\Webhooks\WebhookVerifier;

const WEBHOOK_SECRET = 'whsec_TfB2r8vQ';
const WEBHOOK_TIME = 1761400000;

/*
 * The vector the API's own sender is held to, computed once and checked against Python's hmac
 * and Node's crypto as well, so the two ends can't drift apart unnoticed.
 */
const WEBHOOK_BODY = '{"id":"9f1c","type":"ping","created_at":"2026-10-25T10:42:07+00:00","data":[]}';
const FIXED_VECTOR_BODY = '{"id":"9f1c","type":"ping"}';
const FIXED_VECTOR_DIGEST = '38f9e72dcdf429f3d203c387d4712e009e69d22eeeb8e158508ac8e647ccadb6';

function signedBody(string $body = WEBHOOK_BODY, int $time = WEBHOOK_TIME): string
{
    return (new WebhookVerifier(WEBHOOK_SECRET))->sign($body, $time);
}

describe('a signature that holds up', function () {
    it('matches the fixed vector the sender is held to', function () {
        $header = 't='.WEBHOOK_TIME.',v1='.FIXED_VECTOR_DIGEST;

        expect((new WebhookVerifier(WEBHOOK_SECRET))->sign(FIXED_VECTOR_BODY, WEBHOOK_TIME))->toBe($header);

        // The vector's body isn't a whole event, so it gets past the signature and stops at the reading.
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(FIXED_VECTOR_BODY, $header, WEBHOOK_TIME))
            ->toThrow(UnexpectedResponseException::class);
    });

    it('reads the event of a ping', function () {
        $event = (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, signedBody(), WEBHOOK_TIME);

        expect($event->id)->toBe('9f1c')
            ->and($event->type)->toBe(WebhookEventType::Ping)
            ->and($event->createdAt->format(DATE_ATOM))->toBe('2026-10-25T10:42:07+00:00')
            ->and($event->data)->toBe([])
            ->and($event->image())->toBeNull()
            ->and($event->scan())->toBeNull()
            ->and($event->toArray()['type'])->toBe('ping');
    });

    it('reads the image an image event is about', function (string $type, WebhookEventType $expected) {
        $body = (string) json_encode(['id' => 'd-1', 'type' => $type, 'created_at' => '2026-10-25T10:42:07+00:00', 'data' => Fixtures::image()]);

        $event = (new WebhookVerifier(WEBHOOK_SECRET))->verify($body, signedBody($body), WEBHOOK_TIME);

        expect($event->type)->toBe($expected)
            ->and($event->image()?->status)->toBe(ImageStatus::Completed)
            ->and($event->scan())->toBeNull();
    })->with([
        ['image.completed', WebhookEventType::ImageCompleted],
        ['image.failed', WebhookEventType::ImageFailed],
        ['image.responsive_finished', WebhookEventType::ImageResponsiveFinished],
    ]);

    it('reads the scan a scan event is about', function (string $type, WebhookEventType $expected) {
        $body = (string) json_encode(['id' => 'd-2', 'type' => $type, 'created_at' => '2026-10-25T10:42:07+00:00', 'data' => Fixtures::queuedScan(['status' => 'completed'])]);

        $event = (new WebhookVerifier(WEBHOOK_SECRET))->verify($body, signedBody($body), WEBHOOK_TIME);

        expect($event->type)->toBe($expected)
            ->and($event->scan()?->status)->toBe(ScanStatus::Completed)
            ->and($event->image())->toBeNull();
    })->with([
        ['scan.completed', WebhookEventType::ScanCompleted],
        ['scan.failed', WebhookEventType::ScanFailed],
    ]);

    it('reads an event this version does not know', function () {
        $body = '{"id":"d-3","type":"image.renamed","created_at":"2026-10-25T10:42:07+00:00","data":{"id":"a"}}';

        $event = (new WebhookVerifier(WEBHOOK_SECRET))->verify($body, signedBody($body), WEBHOOK_TIME);

        expect($event->type)->toBe(WebhookEventType::Unknown)
            ->and($event->data)->toBe(['id' => 'a'])
            ->and($event->image())->toBeNull()
            ->and($event->toArray()['type'])->toBe('image.renamed');
    });

    it('takes a time inside the tolerance, either way, and uses the clock when none is given', function () {
        $verifier = new WebhookVerifier(WEBHOOK_SECRET, tolerance: 60);

        expect($verifier->verify(WEBHOOK_BODY, signedBody(), WEBHOOK_TIME + 60)->id)->toBe('9f1c')
            ->and($verifier->verify(WEBHOOK_BODY, signedBody(), WEBHOOK_TIME - 60)->id)->toBe('9f1c')
            ->and($verifier->verify(WEBHOOK_BODY, $verifier->sign(WEBHOOK_BODY))->id)->toBe('9f1c');
    });

    it('reads the two parts in either order and with spaces', function () {
        [$time, $digest] = explode(',', signedBody());

        expect((new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, "{$digest}, {$time}", WEBHOOK_TIME)->id)->toBe('9f1c');
    });
});

describe('a signature that does not hold up', function () {
    it('is refused', function (Closure $signature, ?string $message = null) {
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, $signature(), WEBHOOK_TIME))
            ->toThrow(InvalidWebhookSignatureException::class, $message ?? '');
    })->with([
        'an altered digest' => [fn () => 't='.WEBHOOK_TIME.',v1='.str_repeat('0', 64), 'does not match'],
        'a time other than the one signed' => [fn () => 't='.(WEBHOOK_TIME + 1).','.explode(',', signedBody())[1], 'does not match'],
        'the same time written another way' => [fn () => 't=0'.WEBHOOK_TIME.','.explode(',', signedBody())[1], 'does not match'],
        'no header' => [fn () => null, 'no usable'],
        'an empty header' => [fn () => '', 'no usable'],
        'no time' => [fn () => 'v1='.FIXED_VECTOR_DIGEST, 'no usable'],
        'no digest' => [fn () => 't='.WEBHOOK_TIME, 'no usable'],
        'an empty digest' => [fn () => 't='.WEBHOOK_TIME.',v1=', 'no usable'],
        'a time that is no number' => [fn () => 't=soon,v1='.FIXED_VECTOR_DIGEST, 'no usable'],
        'a negative time' => [fn () => 't=-5,v1='.FIXED_VECTOR_DIGEST, 'no usable'],
        'a time past any integer' => [fn () => 't='.str_repeat('9', 40).',v1='.FIXED_VECTOR_DIGEST, 'no usable'],
        'a header of another kind' => [fn () => 'sha256='.FIXED_VECTOR_DIGEST, 'no usable'],
    ]);

    it('is refused for an altered body', function () {
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY.' ', signedBody(), WEBHOOK_TIME))
            ->toThrow(InvalidWebhookSignatureException::class, 'does not match');
    });

    it('is refused when signed with another secret', function () {
        $signature = (new WebhookVerifier('whsec_other'))->sign(WEBHOOK_BODY, WEBHOOK_TIME);

        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, $signature, WEBHOOK_TIME))
            ->toThrow(InvalidWebhookSignatureException::class, 'does not match');
    });

    it('is refused when the time is stale, or too far ahead', function (int $now) {
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, signedBody(), $now))
            ->toThrow(InvalidWebhookSignatureException::class, 'more than 300 seconds');
    })->with([
        'stale' => [WEBHOOK_TIME + 301],
        'too far ahead' => [WEBHOOK_TIME - 301],
    ]);

    it('can be caught as any other failure of this client', function () {
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify(WEBHOOK_BODY, null))->toThrow(IminifyException::class);
    });
});

describe('a body that was signed and does not read', function () {
    it('ends in the package\'s exception', function (string $body) {
        expect(fn () => (new WebhookVerifier(WEBHOOK_SECRET))->verify($body, signedBody($body), WEBHOOK_TIME))
            ->toThrow(UnexpectedResponseException::class);
    })->with([
        'not JSON' => ['not json'],
        'not an object' => ['5'],
        'no id' => ['{"type":"ping","created_at":"2026-10-25T10:42:07+00:00","data":[]}'],
        'no time' => ['{"id":"a","type":"ping","data":[]}'],
        'no data' => ['{"id":"a","type":"ping","created_at":"2026-10-25T10:42:07+00:00"}'],
    ]);

    it('ends the same way when the record inside it does not read', function () {
        $body = '{"id":"a","type":"image.completed","created_at":"2026-10-25T10:42:07+00:00","data":{"id":"b"}}';

        $event = (new WebhookVerifier(WEBHOOK_SECRET))->verify($body, signedBody($body), WEBHOOK_TIME);

        expect(fn () => $event->image())->toThrow(UnexpectedResponseException::class);
    });
});

describe('the verifier', function () {
    it('refuses a blank secret and a negative tolerance', function () {
        expect(fn () => new WebhookVerifier(''))->toThrow(InvalidArgumentException::class, 'signing secret')
            ->and(fn () => new WebhookVerifier(WEBHOOK_SECRET, -1))->toThrow(InvalidArgumentException::class, 'negative');
    });

    it('keeps the secret out of a dump and out of its messages', function () {
        ob_start();
        var_dump(new WebhookVerifier(WEBHOOK_SECRET));
        $dump = (string) ob_get_clean();

        try {
            (new WebhookVerifier(WEBHOOK_SECRET))->verify('body', 't=1,v1=abc', 1);
            $message = '';
        } catch (InvalidWebhookSignatureException $exception) {
            $message = $exception->getMessage();
        }

        expect($dump)->not->toContain(WEBHOOK_SECRET)
            ->and($message)->toBe('The webhook signature does not match the body and the signing secret.')
            ->and(print_r(new WebhookVerifier(WEBHOOK_SECRET), true))->not->toContain(WEBHOOK_SECRET);
    });

    it('names the header', function () {
        expect(WebhookVerifier::HEADER)->toBe('Iminify-Signature');
    });
});
