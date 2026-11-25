<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * A reserved ISP proxy IP. `proxies` and `proxyCount` are only set when
 * fetching a single IP.
 */
final readonly class ProxyIp extends Data
{
    /**
     * @param  string  $status  "active" or "excluded".
     * @param  list<Reference>  $proxies
     */
    public function __construct(
        public string $ipAddress,
        public string $country,
        public string $status,
        public array $proxies,
        public ?int $proxyCount,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            ipAddress: (string) $data['ip_address'],
            country: (string) $data['country'],
            status: (string) $data['status'],
            proxies: self::many($data['proxies'] ?? [], Reference::class),
            proxyCount: self::int($data['proxy_count'] ?? null),
            raw: $data,
        );
    }
}
