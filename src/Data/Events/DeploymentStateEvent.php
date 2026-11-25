<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Data\Deployment;

final readonly class DeploymentStateEvent extends Event
{
    public function __construct(
        public Deployment $deployment,
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            deployment: Deployment::fromArray($data['deployment']),
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
