<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The request never got an answer: the network failed, or the HTTP client gave up.
 */
class TransportException extends IminifyException {}
