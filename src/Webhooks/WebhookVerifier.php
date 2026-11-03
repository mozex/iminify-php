<?php

declare(strict_types=1);

namespace Mozex\Iminify\Webhooks;

use InvalidArgumentException;
use Mozex\Iminify\Exceptions\InvalidWebhookSignatureException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use SensitiveParameter;

/**
 * Checks that a webhook request came from Iminify. Hand it the raw request body, before any
 * JSON parsing changes it, and the `Iminify-Signature` header:
 *
 *     $event = (new WebhookVerifier($secret))->verify($request->getContent(), $request->header(WebhookVerifier::HEADER));
 */
class WebhookVerifier
{
    public const HEADER = 'Iminify-Signature';

    /**
     * @param  string  $secret  the whole signing secret from Settings > Webhooks, with its `whsec_`
     * @param  int  $tolerance  how many seconds a request's time may be from this server's clock, either way
     */
    public function __construct(
        #[SensitiveParameter]
        protected string $secret,
        protected int $tolerance = 300,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('A webhook signing secret is required. It\'s shown under Settings > Webhooks when the address is saved.');
        }

        if ($tolerance < 0) {
            throw new InvalidArgumentException('The tolerance is a number of seconds, so it can\'t be negative.');
        }
    }

    /**
     * Checks the request and reads its event.
     *
     * @param  string|null  $signature  the header's value, null when the request has none
     * @param  int|null  $now  the time to check against, in seconds since 1970, instead of the clock
     *
     * @throws InvalidWebhookSignatureException when the request can't be trusted
     * @throws UnexpectedResponseException when a signed body doesn't read as an event
     */
    public function verify(string $payload, ?string $signature, ?int $now = null): WebhookEvent
    {
        [$timestamp, $digest] = $this->parse($signature);

        if (! hash_equals($this->digest($timestamp, $payload), $digest)) {
            throw InvalidWebhookSignatureException::mismatch();
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $this->tolerance) {
            throw InvalidWebhookSignatureException::outsideTolerance($this->tolerance);
        }

        return WebhookEvent::fromPayload($payload);
    }

    /**
     * The `Iminify-Signature` header for a body, for sending your own endpoint a signed request in its tests.
     *
     * @param  int|null  $timestamp  in seconds since 1970; the clock when left out
     */
    public function sign(string $payload, ?int $timestamp = null): string
    {
        $timestamp = (string) ($timestamp ?? time());

        return "t={$timestamp},v1={$this->digest($timestamp, $payload)}";
    }

    /**
     * The secret stays out of a var_dump() or print_r().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[hidden]', 'tolerance' => $this->tolerance];
    }

    /**
     * The header reads `t=<seconds>,v1=<lowercase hex>`. The time stays the text that was sent,
     * since that's what was signed, and its length is capped so it can't overflow an integer.
     *
     * @return array{0: string, 1: string}
     */
    protected function parse(?string $signature): array
    {
        $parts = [];

        foreach (explode(',', (string) $signature) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($name)] = trim($value);
        }

        $timestamp = $parts['t'] ?? '';
        $digest = $parts['v1'] ?? '';

        if (preg_match('/\A\d{1,18}\z/', $timestamp) !== 1 || $digest === '') {
            throw InvalidWebhookSignatureException::missing();
        }

        return [$timestamp, $digest];
    }

    protected function digest(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
    }
}
