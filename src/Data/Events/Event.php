<?php

namespace Nietthijmen\LaravelKernel\Data\Events;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Data\Data;

/**
 * An event from a Kernel Server-Sent Events stream.
 */
abstract readonly class Event extends Data
{
    /**
     * Build the matching event class from a decoded SSE payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data): Event
    {
        $class = match ($data['event'] ?? null) {
            'log' => LogEvent::class,
            'deployment_state' => DeploymentStateEvent::class,
            'invocation_state' => InvocationStateEvent::class,
            'app_version_summary' => AppVersionSummaryEvent::class,
            'error' => ErrorEvent::class,
            'sse_heartbeat' => HeartbeatEvent::class,
            default => UnknownEvent::class,
        };

        return $class::fromArray($data);
    }

    protected static function timestamp(mixed $value): CarbonImmutable
    {
        return self::date($value) ?? CarbonImmutable::now();
    }
}
