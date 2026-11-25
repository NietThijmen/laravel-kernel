<?php

namespace Nietthijmen\LaravelKernel;

use Nietthijmen\LaravelKernel\Commands\ListAppsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelKernelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-kernel')
            ->hasConfigFile('kernel')
            ->hasCommand(ListAppsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LaravelKernel::class, fn ($app) => new LaravelKernel(
            apiKey: $app['config']->get('kernel.api_key'),
            baseUrl: (string) $app['config']->get('kernel.base_url', 'https://api.onkernel.com'),
            timeout: (int) $app['config']->get('kernel.timeout', 60),
            retries: (int) $app['config']->get('kernel.retries', 2),
            retryDelay: (int) $app['config']->get('kernel.retry_delay', 500),
        ));

        $this->app->alias(LaravelKernel::class, 'kernel');
    }
}
