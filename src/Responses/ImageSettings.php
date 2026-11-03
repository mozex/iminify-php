<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * What the image was sent with.
 */
class ImageSettings
{
    /**
     * @param  string|null  $format  what was asked for; `auto` stays `auto` after the image finishes
     * @param  list<int>|null  $responsiveWidths
     */
    public function __construct(
        public readonly string $level,
        public readonly ?string $format,
        public readonly bool $keepMetadata,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?int $scale,
        public readonly ?int $sizeLimit,
        public readonly ?array $responsiveWidths,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['level'],
            $attributes['format'] ?? null,
            $attributes['keep_metadata'],
            $attributes['width'] ?? null,
            $attributes['height'] ?? null,
            $attributes['scale'] ?? null,
            $attributes['size_limit'] ?? null,
            $attributes['responsive_widths'] ?? null,
        );
    }
}
