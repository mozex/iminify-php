<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Enums\AltTextStatus;

class AltText
{
    public function __construct(
        public readonly AltTextStatus $status,
        public readonly ?string $text,
        public readonly ?string $language,
        public readonly ?string $suggestedName,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            AltTextStatus::tryFrom($attributes['status'] ?? null) ?? AltTextStatus::Unknown,
            $attributes['text'] ?? null,
            $attributes['language'] ?? null,
            $attributes['suggested_name'] ?? null,
        );
    }
}
