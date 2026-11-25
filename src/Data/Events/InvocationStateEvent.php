<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Data\Invocation;

final readonly class InvocationStateEvent extends Event
{
    public function __construct(
        public Invocation $invocation,
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            invocation: Invocation::fromArray($data['invocation']),
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
