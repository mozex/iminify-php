<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Enums\ResponsiveStatus;

class ResponsiveSet
{
    /**
     * @param  list<ResponsiveFile>  $files
     * @param  string|null  $html  an `<img srcset>` over the files, to paste beside them
     */
    public function __construct(
        public readonly ResponsiveStatus $status,
        public readonly array $files,
        public readonly ?string $downloadUrl,
        public readonly ?string $html,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            ResponsiveStatus::tryFrom($attributes['status']) ?? ResponsiveStatus::Unknown,
            array_values(array_map(fn (array $file): ResponsiveFile => ResponsiveFile::from($file), $attributes['files'] ?? [])),
            $attributes['download_url'] ?? null,
            $attributes['html'] ?? null,
        );
    }
}
