<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

final readonly class BrowserReplay extends Data
{
    public function __construct(
        public string $replayId,
        public ?string $replayViewUrl,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            replayId: (string) $data['replay_id'],
            replayViewUrl: self::string($data['replay_view_url'] ?? null),
            startedAt: self::date($data['started_at'] ?? null),
            finishedAt: self::date($data['finished_at'] ?? null),
            raw: $data,
        );
    }
}
