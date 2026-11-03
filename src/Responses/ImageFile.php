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
            $attributes['name'],
            $attributes['format'],
            $attributes['size'],
            $attributes['width'],
            $attributes['height'],
            $attributes['download_url'],
        );
    }
}
