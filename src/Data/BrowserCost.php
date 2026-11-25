<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;

/**
 * Estimated cost of a browser session (only returned with `include_cost`).
 */
final readonly class BrowserCost extends Data
{
    /**
     * @param  string  $status  provisional, pending or unavailable.
     * @param  string|null  $estimatedUsageSpendUsd  Decimal string, e.g. "0.0123".
     */
    public function __construct(
        public string $status,
        public ?string $estimatedUsageSpendUsd,
        public ?CarbonImmutable $asOf,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            status: (string) $data['status'],
            estimatedUsageSpendUsd: self::string($data['estimated_usage_spend_usd'] ?? null),
            asOf: self::date($data['as_of'] ?? null),
            raw: $data,
        );
    }
}
