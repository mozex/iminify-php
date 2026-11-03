<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * Something failed on Iminify's side (5xx). A 503 did nothing and counted nothing, so the same request can be sent again.
 */
class ServerException extends ApiException {}
