<?php

namespace Nietthijmen\LaravelKernel\Enums;

enum InvocationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Whether the invocation has reached a final state.
     */
    public function isFinished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
