<?php

namespace Nietthijmen\LaravelKernel\Exceptions;

use Nietthijmen\LaravelKernel\Playwright\ScriptResult;
use RuntimeException;

class ScriptFailedException extends RuntimeException
{
    public function __construct(public readonly ScriptResult $result)
    {
        parent::__construct((string) $result->failureMessage());
    }
}
