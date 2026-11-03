<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * Too many requests this minute (429). Wait `$retryAfter` seconds and send the same request again; nothing was done or counted.
 */
class RateLimitedException extends ApiException {}
