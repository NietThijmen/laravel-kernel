<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Proxy;
use Nietthijmen\LaravelKernel\Data\ProxyIp;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/proxies
 */
class Proxies extends BaseResource
{
    /**
     * @param  array{query?: string, name?: string, ip_address?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Proxy>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/proxies', Proxy::class, $query);
    }

    /**
     * @param  array{query?: string, name?: string, ip_address?: string}  $query
     * @return LazyCollection<int, Proxy>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/proxies', Proxy::class, $query);
    }

    /**
     * @param  array<string, mixed>  $data  e.g. ['type' => 'residential', 'name' => 'us-resi']
     */
    public function create(array $data): Proxy
    {
        return Proxy::fromArray($this->postJson('/proxies', $data));
    }

    public function get(string $idOrName): Proxy
    {
        return Proxy::fromArray($this->getJson($this->path('/proxies/%s', $idOrName)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $idOrName, array $data): Proxy
    {
        return Proxy::fromArray($this->patchJson($this->path('/proxies/%s', $idOrName), $data));
    }

    public function delete(string $idOrName): void
    {
        $this->sendDelete($this->path('/proxies/%s', $idOrName));
    }

    /**
     * Run a health check on the proxy and return its updated state.
     *
     * @param  array<string, mixed>  $data
     */
    public function check(string $idOrName, array $data = []): Proxy
    {
        return Proxy::fromArray($this->postJson($this->path('/proxies/%s/check', $idOrName), $data));
    }

    /**
     * List reserved ISP IPs.
     *
     * @param  array{country?: string, status?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<ProxyIp>
     */
    public function ips(array $query = []): PaginatedResponse
    {
        return $this->paginate('/proxy_ips', ProxyIp::class, $query);
    }

    /**
     * Get a reserved ISP IP, including the proxies that use it.
     */
    public function ip(string $ipAddress): ProxyIp
    {
        return ProxyIp::fromArray($this->getJson($this->path('/proxy_ips/%s', $ipAddress)));
    }
}
