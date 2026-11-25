<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

final readonly class Extension extends Data
{
    /**
     * @param  string|null  $checksum  SHA-256 of the extension contents (lowercase hex).
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public int $sizeBytes,
        public ?string $checksum,
        public CarbonImmutable $createdAt,
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
            sizeBytes: (int) $data['size_bytes'],
            checksum: self::string($data['checksum'] ?? null),
            createdAt: CarbonImmutable::parse($data['created_at']),
            lastUsedAt: self::date($data['last_used_at'] ?? null),
            raw: $data,
        );
    }
}
