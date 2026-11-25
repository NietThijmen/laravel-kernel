<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

final readonly class Profile extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ?CarbonImmutable $lastUsedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            name: self::string($data['name'] ?? null),
            createdAt: CarbonImmutable::parse($data['created_at']),
            updatedAt: self::date($data['updated_at'] ?? null),
            lastUsedAt: self::date($data['last_used_at'] ?? null),
            raw: $data,
        );
    }
}
