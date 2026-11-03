<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * An image a scan found and didn't compress.
 */
class SkippedImage
{
    /**
     * @param  string  $reason  such as `duplicate`, `unsupported_format` or `too_large`; every reason is listed at https://www.iminify.com/docs/page-scans
     */
    public function __construct(
        public readonly string $url,
        public readonly string $reason,
        public readonly string $message,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['url'] ?? null,
            $attributes['reason'] ?? null,
            $attributes['message'] ?? null,
        );
    }
}
