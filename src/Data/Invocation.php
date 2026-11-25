<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Enums\InvocationStatus;

final readonly class Invocation extends Data
{
    /**
     * @param  InvocationStatus|null  $status  Null if the API returned a status this package does not know yet (see `$raw['status']`).
     * @param  string|null  $payload  The action input, as a JSON string.
     * @param  string|null  $output  The action output, as a JSON string.
     */
    public function __construct(
        public string $id,
        public string $appName,
        public string $actionName,
        public string $version,
        public ?InvocationStatus $status,
        public ?string $statusReason,
        public ?string $payload,
        public ?string $output,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            appName: (string) $data['app_name'],
            actionName: (string) $data['action_name'],
            version: (string) $data['version'],
            status: InvocationStatus::tryFrom((string) $data['status']),
            statusReason: self::string($data['status_reason'] ?? null),
            payload: self::string($data['payload'] ?? null),
            output: self::string($data['output'] ?? null),
            startedAt: CarbonImmutable::parse($data['started_at']),
            finishedAt: self::date($data['finished_at'] ?? null),
            raw: $data,
        );
    }

    /**
     * The decoded JSON output, or null when there is no (valid JSON) output.
     */
    public function json(): mixed
    {
        return $this->output === null ? null : json_decode($this->output, true);
    }
}
