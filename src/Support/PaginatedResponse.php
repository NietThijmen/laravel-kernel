<?php

namespace Nietthijmen\LaravelKernel\Support;

use ArrayIterator;
use Countable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Kernel list endpoint.
 *
 * Kernel returns list results as a bare JSON array and puts pagination
 * metadata in the X-Has-More, X-Limit, X-Offset and X-Next-Offset headers.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
class PaginatedResponse implements Countable, IteratorAggregate
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $hasMore,
        public readonly ?int $limit,
        public readonly ?int $offset,
        public readonly ?int $nextOffset,
    ) {}

    /**
     * @template TItem
     *
     * @param  callable(array<string, mixed>): TItem  $map
     * @return self<TItem>
     */
    public static function fromResponse(Response $response, callable $map): self
    {
        $int = fn (string $header): ?int => $response->header($header) === ''
            ? null
            : (int) $response->header($header);

        $nextOffset = $int('X-Next-Offset');

        return new self(
            items: array_values(array_map($map, $response->json() ?? [])),
            hasMore: filter_var($response->header('X-Has-More'), FILTER_VALIDATE_BOOLEAN),
            limit: $int('X-Limit'),
            offset: $int('X-Offset'),
            nextOffset: $nextOffset,
        );
    }

    /**
     * @return Collection<int, T>
     */
    public function collect(): Collection
    {
        return new Collection($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
