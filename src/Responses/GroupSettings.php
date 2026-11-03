<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * What an upload group was opened with.
 */
class GroupSettings
{
    /**
     * @param  list<string>  $formats  `original`, `webp`, `avif`
     * @param  string  $priority  `interactive`, `background`
     */
    public function __construct(
        public readonly string $level,
        public readonly array $formats,
        public readonly bool $keepMetadata,
        public readonly bool $ephemeral,
        public readonly string $priority,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['level'] ?? null,
            $attributes['formats'] ?? null,
            $attributes['keep_metadata'] ?? null,
            $attributes['ephemeral'] ?? null,
            $attributes['priority'] ?? null,
        );
    }
}
