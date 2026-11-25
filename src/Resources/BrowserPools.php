<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\BrowserPool;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/browser-pools
 */
class BrowserPools extends BaseResource
{
    /**
     * @param  array{query?: string, name?: string, region?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<BrowserPool>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/browser_pools', BrowserPool::class, $query);
    }

    /**
     * @param  array{query?: string, name?: string, region?: string}  $query
     * @return LazyCollection<int, BrowserPool>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/browser_pools', BrowserPool::class, $query);
    }

    /**
     * Create a browser pool, e.g. ['name' => 'scrapers', 'size' => 5, 'headless' => true].
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BrowserPool
    {
        return BrowserPool::fromArray($this->postJson('/browser_pools', $data));
    }

    public function get(string $idOrName): BrowserPool
    {
        return BrowserPool::fromArray($this->getJson($this->path('/browser_pools/%s', $idOrName)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $idOrName, array $data): BrowserPool
    {
        return BrowserPool::fromArray($this->patchJson($this->path('/browser_pools/%s', $idOrName), $data));
    }

    /**
     * @param  bool  $force  Also delete when browsers are leased (they are terminated).
     */
    public function delete(string $idOrName, bool $force = false): void
    {
        $this->sendDelete($this->path('/browser_pools/%s', $idOrName), $force ? ['force' => true] : null);
    }

    /**
     * Acquire a browser from the pool. This long-polls; returns null when the
     * acquire timed out without a browser becoming available.
     *
     * @param  array<string, mixed>  $data  e.g. ['acquire_timeout_seconds' => 30]
     */
    public function acquire(string $idOrName, array $data = []): ?Browser
    {
        $browser = $this->postJson($this->path('/browser_pools/%s/acquire', $idOrName), $data);

        return is_array($browser) ? $this->hydrate(Browser::class, $browser) : null;
    }

    /**
     * Release a browser back to the pool.
     *
     * @param  bool|null  $reuse  False destroys the browser and creates a fresh one.
     */
    public function release(string $idOrName, string $sessionId, ?bool $reuse = null): void
    {
        $this->postJson($this->path('/browser_pools/%s/release', $idOrName), $this->filter([
            'session_id' => $sessionId,
            'reuse' => $reuse,
        ]));
    }

    /**
     * Destroy all idle browsers in the pool.
     */
    public function flush(string $idOrName): void
    {
        $this->postJson($this->path('/browser_pools/%s/flush', $idOrName));
    }
}
