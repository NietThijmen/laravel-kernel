<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Base class for typed Kernel API responses.
 *
 * Every object keeps the payload it was built from in `$raw`, so fields
 * that are not (yet) modelled as properties are still accessible, and
 * `toArray()` / `json_encode()` return exactly what the API returned.
 *
 * @implements Arrayable<string, mixed>
 */
abstract readonly class Data implements Arrayable, JsonSerializable
{
    /**
     * The raw API payload this object was built from.
     *
     * @var array<string, mixed>
     */
    public array $raw;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(array $raw = [])
    {
        $this->raw = $raw;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    protected static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected static function one(mixed $value, string $class): ?Data
    {
        return is_array($value) ? $class::fromArray($value) : null;
    }

    /**
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    protected static function many(mixed $values, string $class): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(
            fn (array $value) => $class::fromArray($value),
            array_filter($values, is_array(...)),
        ));
    }

    protected static function string(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    protected static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    protected static function bool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }
}
