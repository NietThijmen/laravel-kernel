<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * A reference to another resource by ID and/or name
 * (e.g. a browser's pool, a vault, or a pool's extensions).
 */
final readonly class Reference extends Data
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: self::string($data['id'] ?? null),
            name: self::string($data['name'] ?? null),
            raw: $data,
        );
    }
}
