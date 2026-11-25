<?php

namespace Nietthijmen\LaravelKernel\Data;

use Nietthijmen\LaravelKernel\Enums\InvocationStatus;

/**
 * The response of invoking an action (`POST /invocations`).
 *
 * For synchronous invocations `output` holds the result; for asynchronous
 * ones poll `invocations()->get($id)` or stream `invocations()->events($id)`.
 */
final readonly class InvokeResult extends Data
{
    public function __construct(
        public string $id,
        public string $actionName,
        public ?InvocationStatus $status,
        public ?string $statusReason,
        public ?string $output,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            actionName: (string) $data['action_name'],
            status: InvocationStatus::tryFrom((string) $data['status']),
            statusReason: self::string($data['status_reason'] ?? null),
            output: self::string($data['output'] ?? null),
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
