<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;

final readonly class ErrorEvent extends Event
{
    /**
     * @param  list<array{code?: string, message?: string}>  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $details,
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            code: (string) ($data['error']['code'] ?? ''),
            message: (string) ($data['error']['message'] ?? ''),
            details: array_values($data['error']['details'] ?? []),
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
