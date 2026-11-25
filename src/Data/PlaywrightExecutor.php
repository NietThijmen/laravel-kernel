<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

/**
 * A Node.js process that runs Playwright code for a browser. Calls on the
 * same executor run one at a time; different executors run concurrently.
 */
final readonly class PlaywrightExecutor extends Data
{
    /**
     * @param  string|null  $targetId  CDP target ID of the tab a named executor owns.
     * @param  string|null  $url  URL of that tab.
     */
    public function __construct(
        public string $name,
        public bool $busy,
        public ?string $targetId,
        public ?string $url,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $lastUsedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            name: (string) $data['name'],
            busy: (bool) $data['busy'],
            targetId: self::string($data['target_id'] ?? null),
            url: self::string($data['url'] ?? null),
            createdAt: CarbonImmutable::parse($data['created_at']),
            lastUsedAt: CarbonImmutable::parse($data['last_used_at']),
            raw: $data,
        );
    }
}
