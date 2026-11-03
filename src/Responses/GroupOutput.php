<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Enums\ImageStatus;

/**
 * One output of an upload group in one of its formats.
 */
class GroupOutput
{
    /**
     * @param  string  $format  `original`, `webp`, `avif`
     * @param  string|null  $file  its name in the zip, when it's there
     * @param  int|null  $size  in bytes
     * @param  bool  $replace  whether to write it over your copy: it's in the zip only when true
     */
    public function __construct(
        public readonly string $name,
        public readonly string $format,
        public readonly ImageStatus $status,
        public readonly ?string $file,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?int $size,
        public readonly bool $replace,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['name'] ?? null,
            $attributes['format'] ?? null,
            ImageStatus::tryFrom($attributes['status'] ?? null) ?? ImageStatus::Unknown,
            $attributes['file'] ?? null,
            $attributes['width'] ?? null,
            $attributes['height'] ?? null,
            $attributes['size'] ?? null,
            $attributes['replace'] ?? null,
        );
    }
}
