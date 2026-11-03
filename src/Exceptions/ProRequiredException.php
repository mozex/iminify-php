<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The call needs a paid plan (403).
 */
class ProRequiredException extends AccessDeniedException {}
