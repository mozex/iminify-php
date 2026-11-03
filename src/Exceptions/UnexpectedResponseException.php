<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * The answer isn't one the API gives: a redirect, or a body that doesn't read as expected.
 */
class UnexpectedResponseException extends IminifyException
{
    public static function redirect(int $status, ?string $location): self
    {
        return new self("Iminify answered HTTP {$status} with a redirect to ".($location ?? 'nowhere').'. Set the base URI to the address the API answers at: following a redirect can turn a POST into a GET.');
    }

    public static function notJson(int $status): self
    {
        return new self("Iminify answered HTTP {$status} with a body that isn't a JSON object.");
    }

    public static function missingData(): self
    {
        return new self('Iminify answered without the `data` key its answers carry.');
    }
}
