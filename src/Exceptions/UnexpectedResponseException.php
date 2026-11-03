<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

use Throwable;

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

    public static function unreadable(Throwable $previous): self
    {
        return new self('Iminify answered with a record this client could not read: a field it needs is missing or of another type.', 0, $previous);
    }

    public static function missingData(): self
    {
        return new self('Iminify answered without the `data` key its answers carry.');
    }

    public static function incompleteDownload(int $written, ?int $size): self
    {
        return new self("The download ended after {$written} bytes".($size === null ? '.' : " of {$size}."));
    }
}
