<?php

namespace Nietthijmen\LaravelKernel\Tests;

use Nietthijmen\LaravelKernel\LaravelKernelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelKernelServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('kernel.api_key', 'test-key');
        config()->set('kernel.retry_delay', 0);
    }
}
