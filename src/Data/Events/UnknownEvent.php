<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

/**
 * An event type this package does not know yet. Inspect `$raw`.
 */
final readonly class UnknownEvent extends Event
{
    public function __construct(
        public ?string $event,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            event: self::string($data['event'] ?? null),
            raw: $data,
        );
    }
}
