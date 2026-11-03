<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * One of an image's two files. `$downloadUrl` needs the API key, which only the client's
 * own download methods send.
 */
class ImageFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $format,
        public readonly int $size,
        public readonly int $width,
        public readonly int $height,
        public readonly string $downloadUrl,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['name'] ?? null,
            $attributes['format'] ?? null,
            $attributes['size'] ?? null,
            $attributes['width'] ?? null,
            $attributes['height'] ?? null,
            $attributes['download_url'] ?? null,
        );
    }
}
