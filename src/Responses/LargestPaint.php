<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * What the browser measured as the page's largest contentful paint.
 */
class LargestPaint
{
    /**
     * @param  string  $kind  `image`, `text`, `none`
     * @param  string|null  $url  the image's address, when it's an image
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $element,
        public readonly ?string $url,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['kind'] ?? null,
            $attributes['element'] ?? null,
            $attributes['url'] ?? null,
        );
    }
}
