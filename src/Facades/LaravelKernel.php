<?php

namespace Nietthijmen\LaravelKernel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Nietthijmen\LaravelKernel\Resources\Apps apps()
 * @method static \Nietthijmen\LaravelKernel\Resources\Deployments deployments()
 * @method static \Nietthijmen\LaravelKernel\Resources\Invocations invocations()
 * @method static \Nietthijmen\LaravelKernel\Resources\Browsers browsers()
 * @method static \Nietthijmen\LaravelKernel\Resources\BrowserPools browserPools()
 * @method static \Nietthijmen\LaravelKernel\Resources\Profiles profiles()
 * @method static \Nietthijmen\LaravelKernel\Resources\Proxies proxies()
 * @method static \Nietthijmen\LaravelKernel\Resources\Extensions extensions()
 * @method static \Nietthijmen\LaravelKernel\LaravelKernel withApiKey(string $apiKey)
 * @method static \Illuminate\Http\Client\PendingRequest http()
 * @method static \Illuminate\Http\Client\Response request(string $method, string $uri, array $query = [], ?array $json = null)
 *
 * @see \Nietthijmen\LaravelKernel\LaravelKernel
 */
class LaravelKernel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nietthijmen\LaravelKernel\LaravelKernel::class;
    }
}
