<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;

/**
 * Keep-alive event. Only yielded when heartbeats are requested.
 */
final readonly class HeartbeatEvent extends Event
{
    public function __construct(
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
