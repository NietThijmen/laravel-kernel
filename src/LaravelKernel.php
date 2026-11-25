<?php

namespace Nietthijmen\LaravelKernel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Nietthijmen\LaravelKernel\Exceptions\KernelException;
use Nietthijmen\LaravelKernel\Resources\Apps;
use Nietthijmen\LaravelKernel\Resources\BrowserPools;
use Nietthijmen\LaravelKernel\Resources\Browsers;
use Nietthijmen\LaravelKernel\Resources\Deployments;
use Nietthijmen\LaravelKernel\Resources\Extensions;
use Nietthijmen\LaravelKernel\Resources\Invocations;
use Nietthijmen\LaravelKernel\Resources\Profiles;
use Nietthijmen\LaravelKernel\Resources\Proxies;
use RuntimeException;
use Throwable;

/**
 * Client for the Kernel API (https://www.kernel.sh/docs/api-reference).
 */
class LaravelKernel
{
    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl = 'https://api.onkernel.com',
        protected int $timeout = 60,
        protected int $retries = 2,
        protected int $retryDelay = 500,
    ) {}

    public function apps(): Apps
    {
        return new Apps($this);
    }

    public function deployments(): Deployments
    {
        return new Deployments($this);
    }

    public function invocations(): Invocations
    {
        return new Invocations($this);
    }

    public function browsers(): Browsers
    {
        return new Browsers($this);
    }

    public function browserPools(): BrowserPools
    {
        return new BrowserPools($this);
    }

    public function profiles(): Profiles
    {
        return new Profiles($this);
    }

    public function proxies(): Proxies
    {
        return new Proxies($this);
    }

    public function extensions(): Extensions
    {
        return new Extensions($this);
    }

    /**
     * Return a copy of the client that uses a different API key.
     */
    public function withApiKey(string $apiKey): static
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey;

        return $clone;
    }

    /**
     * A pre-configured HTTP request (base URL, auth, timeout, retries).
     */
    public function http(): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('No Kernel API key configured. Set KERNEL_API_KEY in your .env file.');
        }

        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(
                times: $this->retries + 1,
                sleepMilliseconds: $this->retryDelay,
                when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->status() === 429),
                throw: false,
            );
    }

    /**
     * Send a request to any Kernel API endpoint. Useful for endpoints that
     * do not have a dedicated resource method yet.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    public function request(string $method, string $uri, array $query = [], ?array $json = null): Response
    {
        $options = array_filter([
            'query' => $query === [] ? null : $query,
            'json' => $json,
        ], fn ($value) => $value !== null);

        return $this->ensureSuccessful(
            $this->http()->send(strtoupper($method), $uri, $options)
        );
    }

    /**
     * @throws KernelException
     */
    public function ensureSuccessful(Response $response): Response
    {
        if ($response->failed()) {
            throw KernelException::fromResponse($response);
        }

        return $response;
    }
}
