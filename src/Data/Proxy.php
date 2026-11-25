<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Enums\ProxyStatus;
use Nietthijmen\LaravelKernel\Enums\ProxyType;

final readonly class Proxy extends Data
{
    /**
     * @param  list<string>  $bypassHosts
     * @param  array<string, mixed>|null  $config  Type-specific configuration (country, city, host, ...).
     */
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?ProxyType $type,
        public ?ProxyStatus $status,
        public ?string $protocol,
        public ?string $ipAddress,
        public array $bypassHosts,
        public ?array $config,
        public ?CarbonImmutable $lastChecked,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: self::string($data['id'] ?? null),
            name: self::string($data['name'] ?? null),
            type: ProxyType::tryFrom((string) $data['type']),
            status: ProxyStatus::tryFrom((string) ($data['status'] ?? '')),
            protocol: self::string($data['protocol'] ?? null),
            ipAddress: self::string($data['ip_address'] ?? null),
            bypassHosts: array_values($data['bypass_hosts'] ?? []),
            config: $data['config'] ?? null,
            lastChecked: self::date($data['last_checked'] ?? null),
            raw: $data,
        );
    }
}
