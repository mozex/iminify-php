<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

class ResponsiveFile
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly int $size,
        public readonly string $name,
        public readonly string $downloadUrl,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['width'],
            $attributes['height'],
            $attributes['size'],
            $attributes['name'],
            $attributes['download_url'],
        );
    }
}
