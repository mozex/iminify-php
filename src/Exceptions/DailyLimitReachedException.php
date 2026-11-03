<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The day's allowance is spent (429). `$retryAfter` holds the seconds until it opens again, which can be hours, so decide whether to wait or stop.
 */
class DailyLimitReachedException extends ApiException {}
