<?php

declare(strict_types=1);

namespace Mozex\Iminify\Http;

use BackedEnum;
use InvalidArgumentException;

/**
 * One call to the API, before it becomes an HTTP request. Parameters pass through
 * unchecked, so a parameter the API adds works without a new release of the client.
 */
class ApiRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $fields  the form fields sent beside an upload
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?array $json = null,
        public readonly array $fields = [],
        public readonly ?Upload $upload = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function get(string $path, array $query = []): self
    {
        return new self('GET', $path, self::clean($query));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public static function post(string $path, array $json = []): self
    {
        return new self('POST', $path, json: self::clean($json));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public static function patch(string $path, array $json): self
    {
        return new self('PATCH', $path, json: self::clean($json));
    }

    public static function delete(string $path): self
    {
        return new self('DELETE', $path);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function upload(string $path, Upload $upload, array $fields = []): self
    {
        return new self('POST', $path, fields: self::clean($fields), upload: $upload);
    }

    /**
     * A path segment from a caller's value. Encoding keeps a slash from reaching another
     * address, and a blank or dotted id would still resolve to the collection or its parent.
     */
    public static function segment(string $value): string
    {
        if (trim($value, " \t\r\n.") === '') {
            throw new InvalidArgumentException('An id must not be blank.');
        }

        return rawurlencode($value);
    }

    /**
     * A null means the same as leaving the parameter out, which is all a form can say.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected static function clean(array $parameters): array
    {
        $clean = [];

        foreach ($parameters as $name => $value) {
            if ($value !== null) {
                $clean[$name] = $value instanceof BackedEnum ? $value->value : $value;
            }
        }

        return $clean;
    }
}
