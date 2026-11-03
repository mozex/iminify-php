<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Closure;
use Exception;
use Mozex\Iminify\Contracts\TransporterContract;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use TypeError;

abstract class ApiResource
{
    /** The seconds before the second look at a record, growing to `MAX_POLL_DELAY`. */
    protected const FIRST_POLL_DELAY = 1.0;

    protected const MAX_POLL_DELAY = 5.0;

    /**
     * @param  (Closure(float): void)|null  $sleep  what waiting calls instead of `usleep()`
     */
    public function __construct(
        protected TransporterContract $transporter,
        protected ?Closure $sleep = null,
    ) {}

    /**
     * Reads a record until `$until` says it's done, backing off between looks. A `rate_limited`
     * answer waits out its `Retry-After`; anything else thrown reaches the caller. The clock counts
     * what was slept as well as what passed, so a sleep that returns at once still ends the wait.
     *
     * @template TRecord
     *
     * @param  Closure(): TRecord  $read
     * @param  Closure(TRecord): bool  $until
     * @return TRecord
     *
     * @throws WaitTimeoutException when the next wait wouldn't fit in `$timeout` seconds
     */
    protected function poll(Closure $read, Closure $until, int $timeout, string $what, mixed $last = null): mixed
    {
        $started = hrtime(true);
        $slept = 0.0;
        $delay = static::FIRST_POLL_DELAY;

        while (true) {
            try {
                $last = $read();

                if ($until($last)) {
                    return $last;
                }

                $wait = $delay;
                $delay = min($delay * 1.5, static::MAX_POLL_DELAY);
            } catch (RateLimitedException $exception) {
                $wait = (float) max(1, $exception->retryAfter ?? $delay);
            }

            $elapsed = max($slept, (hrtime(true) - $started) / 1e9);

            if ($elapsed + $wait > $timeout) {
                throw WaitTimeoutException::after($timeout, $what, $last);
            }

            $this->pause($wait);
            $slept += $wait;
        }
    }

    /**
     * Builds a response from an answer, so an answer that doesn't read as the API's document
     * says, such as a proxy's or a redirect's, ends in this package's exception.
     *
     * @template TResponse
     *
     * @param  Closure(): TResponse  $build
     * @return TResponse
     */
    protected function parse(Closure $build): mixed
    {
        try {
            return $build();
        } catch (IminifyException $exception) {
            throw $exception;
        } catch (TypeError|Exception $exception) {
            throw UnexpectedResponseException::unreadable($exception);
        }
    }

    protected function pause(float $seconds): void
    {
        if ($this->sleep !== null) {
            ($this->sleep)($seconds);

            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
