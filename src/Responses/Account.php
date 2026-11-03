<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use DateTimeImmutable;
use Mozex\Iminify\Enums\Plan;

/**
 * The key's account: its plan, what the plan allows, and how much of the day's allowance is gone.
 */
class Account
{
    /**
     * @param  DateTimeImmutable|null  $planEndsAt  when a paid plan or a trial ends; null while a paid plan renews or on the free plan
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly Plan $plan,
        public readonly ?DateTimeImmutable $planEndsAt,
        public readonly AccountLimits $limits,
        public readonly AccountUsage $usage,
        public readonly RateLimit $rateLimit,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function from(array $attributes, ?RateLimit $rateLimit = null): self
    {
        return new self(
            $attributes['name'] ?? null,
            $attributes['email'] ?? null,
            Plan::tryFrom($attributes['plan'] ?? null) ?? Plan::Unknown,
            isset($attributes['plan_ends_at']) ? new DateTimeImmutable($attributes['plan_ends_at']) : null,
            AccountLimits::from($attributes['limits'] ?? null),
            AccountUsage::from($attributes['usage'] ?? null),
            $rateLimit ?? new RateLimit,
            $attributes,
        );
    }

    /**
     * The answer as the API sent it, field names and any field this client doesn't know included.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
