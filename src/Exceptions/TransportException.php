<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * Iminify couldn't be reached, a request timed out, a response broke off, or a file to upload
 * broke off or changed while being read.
 */
class TransportException extends IminifyException {}
