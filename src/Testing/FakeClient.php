<?php

declare(strict_types=1);

namespace Mozex\Iminify\Testing;

use Closure;
use LogicException;
use Mozex\Iminify\Client;
use Mozex\Iminify\Contracts\TransporterContract;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\ApiResponse;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Stands in for the client in your tests. The real resources read answers you queue instead
 * of calling the API, so an answer reads, and a refusal throws, as it would for real. Every
 * call is kept to assert on, and waiting never really sleeps.
 *
 * Each answer is one of:
 *
 * - an array: the record, sent under `data`, such as `Fixtures::image()`. An array that
 *   already has a `data` key is the whole answer, such as `Fixtures::page([...])`.
 * - an `ApiResponse`: sent as it stands, such as `Fixtures::file()`, `Fixtures::noContent()`
 *   or `Fixtures::refusal('daily_limit_reached', 429)`.
 * - an exception: thrown, such as a `TransportException` for a lost connection.
 *
 * @phpstan-type Answer array<array-key, mixed>|ApiResponse|Throwable
 */
class FakeClient extends Client implements TransporterContract
{
    /** @var list<ApiRequest> */
    protected array $requests = [];

    /** @var list<Answer> */
    protected array $answers = [];

    /**
     * @param  array<array-key, Answer>  $answers  in the order the calls will ask for them
     */
    public function __construct(array $answers = [])
    {
        parent::__construct($this, static function (float $seconds): void {});

        $this->addAnswers($answers);
    }

    /**
     * @param  array<array-key, Answer>  $answers
     */
    public function addAnswers(array $answers): static
    {
        $this->answers = [...$this->answers, ...array_values($answers)];

        return $this;
    }

    public function send(ApiRequest $request): ApiResponse
    {
        $this->requests[] = $request;

        if ($this->answers === []) {
            throw new LogicException("No answer is queued for {$request->method} {$request->path}. Pass one to the FakeClient or to addAnswers().");
        }

        $answer = array_shift($this->answers);

        return match (true) {
            $answer instanceof Throwable => throw $answer,
            $answer instanceof ApiResponse => $answer->successful(),
            default => Fixtures::answer(array_key_exists('data', $answer) ? $answer : ['data' => $answer])->successful(),
        };
    }

    /**
     * Every call sent so far, oldest first.
     *
     * @return list<ApiRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * The calls sent to this address. A `*` in the path stands for one part of it, as in
     * `images/ * /cancel` written without the spaces.
     *
     * @param  (Closure(ApiRequest): bool)|null  $callback  for what else the call has to be
     * @return list<ApiRequest>
     */
    public function sent(string $method, string $path, ?Closure $callback = null): array
    {
        $pattern = '#\A'.str_replace('\*', '[^/]+', preg_quote(ltrim($path, '/'), '#')).'\z#';

        return array_values(array_filter(
            $this->requests,
            fn (ApiRequest $request): bool => $request->method === strtoupper($method)
                && preg_match($pattern, $request->path) === 1
                && ($callback === null || $callback($request)),
        ));
    }

    /**
     * @param  (Closure(ApiRequest): bool)|null  $callback
     */
    public function assertSent(string $method, string $path, ?Closure $callback = null): void
    {
        Assert::assertNotEmpty($this->sent($method, $path, $callback), "Expected a call to {$method} {$path} that matches, and none was sent.");
    }

    /**
     * @param  (Closure(ApiRequest): bool)|null  $callback
     */
    public function assertNotSent(string $method, string $path, ?Closure $callback = null): void
    {
        Assert::assertEmpty($this->sent($method, $path, $callback), "Expected no call to {$method} {$path} that matches, and one was sent.");
    }

    public function assertSentTimes(string $method, string $path, int $times): void
    {
        Assert::assertCount($times, $this->sent($method, $path), "Expected {$times} calls to {$method} {$path}.");
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->requests, 'Expected no calls to the API, and '.count($this->requests).' were sent.');
    }
}
