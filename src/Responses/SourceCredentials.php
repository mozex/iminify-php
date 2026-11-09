<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

/**
 * The Content Credentials an upload group's source carried. Every file made from it leaves them
 * out; your own copy, and an output handed back as the source, keep them.
 */
class SourceCredentials
{
    public function __construct(
        public readonly bool $generativeAi,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['generative_ai'] ?? null,
        );
    }
}
