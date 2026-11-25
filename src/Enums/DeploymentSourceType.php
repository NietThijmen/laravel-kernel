<?php

namespace Nietthijmen\LaravelKernel\Enums;

enum DeploymentSourceType: string
{
    case File = 'file';
    case Github = 'github';
}
