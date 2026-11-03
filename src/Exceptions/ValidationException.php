<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * Something in the request is missing or wrong (422). `$errors` names each field.
 */
class ValidationException extends ApiException {}
