<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Data\AppAction;

/**
 * Sent when a deployment produced an app version.
 */
final readonly class AppVersionSummaryEvent extends Event
{
    /**
     * @param  list<AppAction>  $actions
     */
    public function __construct(
        public string $id,
        public string $appName,
        public string $version,
        public string $region,
        public array $actions,
        public CarbonImmutable $timestamp,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            appName: (string) $data['app_name'],
            version: (string) $data['version'],
            region: (string) $data['region'],
            actions: self::many($data['actions'] ?? [], AppAction::class),
            timestamp: self::timestamp($data['timestamp'] ?? null),
            raw: $data,
        );
    }
}
