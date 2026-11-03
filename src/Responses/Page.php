<?php

declare(strict_types=1);

namespace Mozex\Iminify\Responses;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list. Loop over it for its records, and ask for `$nextPage` while it isn't null.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     * @param  array<array-key, mixed>  $attributes  the answer as the API sent it
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly ?int $nextPage,
        public readonly RateLimit $rateLimit,
        protected readonly array $attributes,
    ) {}

    /**
     * @template TFrom
     *
     * @param  array<array-key, mixed>  $body
     * @param  Closure(array<array-key, mixed>): TFrom  $item
     * @return self<TFrom>
     */
    public static function from(array $body, Closure $item, ?RateLimit $rateLimit = null): self
    {
        $meta = $body['meta'] ?? [];
        $currentPage = $meta['current_page'] ?? 1;
        $lastPage = $meta['last_page'] ?? $currentPage;

        return new self(
            array_values(array_map(fn (array $attributes) => $item($attributes), $body['data'] ?? null)),
            $currentPage,
            $lastPage,
            $meta['per_page'] ?? 0,
            $meta['total'] ?? 0,
            $currentPage < $lastPage ? $currentPage + 1 : null,
            $rateLimit ?? new RateLimit,
            $body,
        );
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
