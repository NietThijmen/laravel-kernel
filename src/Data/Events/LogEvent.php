<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;

final readonly class LogEvent extends Event
{
    public function __construct(
        public string $message,
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            message: (string) $data['message'],
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
