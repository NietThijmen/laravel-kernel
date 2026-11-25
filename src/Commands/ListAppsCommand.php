<?php

namespace Nietthijmen\LaravelKernel\Commands;

use Illuminate\Console\Command;
use Nietthijmen\LaravelKernel\Data\App;
use Nietthijmen\LaravelKernel\Exceptions\KernelException;
use Nietthijmen\LaravelKernel\LaravelKernel;

class ListAppsCommand extends Command
{
    public $signature = 'kernel:apps
        {--app= : Filter by app name}
        {--app-version= : Filter by version label}
        {--search= : Search apps by name}';

    public $description = 'List the apps deployed to Kernel';

    public function handle(LaravelKernel $kernel): int
    {
        try {
            $apps = $kernel->apps()->all(array_filter([
                'app_name' => $this->option('app'),
                'version' => $this->option('app-version'),
                'query' => $this->option('search'),
            ]))->collect();
        } catch (KernelException $e) {
            $this->error("Kernel API error ({$e->status}): {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($apps->isEmpty()) {
            $this->info('No apps found.');

            return self::SUCCESS;
        }

        $this->table(
            ['App', 'Version', 'Region', 'Deployment', 'Actions'],
            $apps->map(fn (App $app) => [
                $app->appName,
                $app->version,
                $app->region,
                $app->deploymentId,
                implode(', ', array_map(fn ($action) => $action->name, $app->actions)),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
