<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Enums\Region;

final readonly class BrowserPool extends Data
{
    /**
     * @param  list<string>  $extensionIds
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?Region $region,
        public int $availableCount,
        public int $acquiredCount,
        public BrowserPoolConfig $config,
        public array $extensionIds,
        public ?string $profileId,
        public CarbonImmutable $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            name: self::string($data['name'] ?? null),
            region: Region::tryFrom((string) $data['region']),
            availableCount: (int) $data['available_count'],
            acquiredCount: (int) $data['acquired_count'],
            config: BrowserPoolConfig::fromArray($data['browser_pool_config']),
            extensionIds: array_values($data['extension_ids'] ?? []),
            profileId: self::string($data['profile_id'] ?? null),
            createdAt: CarbonImmutable::parse($data['created_at']),
            raw: $data,
        );
    }
}
