<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use Mozex\Iminify\Enums\ContentCredentialsStatus;

class ContentCredentials
{
    public function __construct(
        public readonly ContentCredentialsStatus $status,
        public readonly bool $generativeAi,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            ContentCredentialsStatus::tryFrom($attributes['status'] ?? null) ?? ContentCredentialsStatus::Unknown,
            $attributes['generative_ai'] ?? null,
        );
    }
}
