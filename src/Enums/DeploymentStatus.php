<?php

namespace Nietthijmen\LaravelKernel\Enums;

enum DeploymentStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Running = 'running';
    case Failed = 'failed';
    case Stopped = 'stopped';

    /**
     * Whether the deployment is done building (running, failed or stopped).
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Running, self::Failed, self::Stopped], true);
    }
}
