<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

final readonly class LiveView extends Data
{
    /**
     * @param  string  $scope  "read" or "control".
     */
    public function __construct(
        public string $url,
        public string $scope,
        public ?CarbonImmutable $expiresAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            url: (string) $data['url'],
            scope: (string) $data['scope'],
            expiresAt: self::date($data['expires_at'] ?? null),
            raw: $data,
        );
    }
}
