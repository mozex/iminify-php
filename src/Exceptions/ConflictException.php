<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The record isn't in a state that allows this yet (409). `$errorCode` says which state.
 */
class ConflictException extends ApiException {}
