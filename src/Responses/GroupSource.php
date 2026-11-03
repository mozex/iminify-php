<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * The image an upload group stands for, as it was declared.
 */
class GroupSource
{
    /**
     * @param  int  $size  in bytes
     */
    public function __construct(
        public readonly string $format,
        public readonly int $size,
        public readonly int $width,
        public readonly int $height,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['format'] ?? null,
            $attributes['size'] ?? null,
            $attributes['width'] ?? null,
            $attributes['height'] ?? null,
        );
    }
}
