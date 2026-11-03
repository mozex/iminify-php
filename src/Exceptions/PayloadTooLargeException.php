<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The request body is too large to accept at all (413).
 */
class PayloadTooLargeException extends ApiException {}
