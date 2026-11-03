<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

use RuntimeException;

/**
 * Anything that went wrong while talking to Iminify. Catch this one to handle them all.
 */
class IminifyException extends RuntimeException {}
