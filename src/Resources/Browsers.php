<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\CurlResult;
use Nietthijmen\LaravelKernel\Data\LiveView;
use Nietthijmen\LaravelKernel\Data\Profile;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/browsers
 */
class Browsers extends BaseResource
{
    /**
     * @param  array{status?: 'active'|'deleted'|'all', query?: string, tags?: array<string, string>, region?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Browser>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/browsers', Browser::class, $query);
    }

    /**
     * @param  array{status?: 'active'|'deleted'|'all', query?: string, tags?: array<string, string>, region?: string}  $query
     * @return LazyCollection<int, Browser>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/browsers', Browser::class, $query);
    }

    /**
     * Create a browser session, e.g. ['headless' => true, 'timeout_seconds' => 300].
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data = []): Browser
    {
        return $this->hydrate(Browser::class, $this->postJson('/browsers', $data));
    }

    /**
     * @param  array{include_deleted?: bool, include_cost?: bool}  $query
     */
    public function get(string $idOrName, array $query = []): Browser
    {
        return $this->hydrate(Browser::class, $this->getJson($this->path('/browsers/%s', $idOrName), $query));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $idOrName, array $data): Browser
    {
        return $this->hydrate(Browser::class, $this->patchJson($this->path('/browsers/%s', $idOrName), $data));
    }

    /**
     * Computer controls (mouse, keyboard, screenshots) for a browser session.
     */
    public function computer(string $idOrName): Computer
    {
        return new Computer($this->kernel, $idOrName);
    }

    /**
     * Run Playwright code / scripts against a browser session.
     */
    public function playwright(string $idOrName): Playwright
    {
        return new Playwright($this->kernel, $idOrName);
    }

    public function delete(string $idOrName): void
    {
        $this->sendDelete($this->path('/browsers/%s', $idOrName));
    }

    /**
     * Make an HTTP request through the browser's network stack.
     *
     * @param  array<string, mixed>  $data  e.g. ['url' => 'https://example.com', 'method' => 'GET']
     */
    public function curl(string $idOrName, array $data): CurlResult
    {
        return CurlResult::fromArray($this->postJson($this->path('/browsers/%s/curl', $idOrName), $data));
    }

    /**
     * Create a live view URL for the browser session.
     *
     * @param  array<string, mixed>  $data
     */
    public function liveView(string $idOrName, array $data = []): LiveView
    {
        return LiveView::fromArray($this->postJson($this->path('/browsers/%s/live_view', $idOrName), $data));
    }

    /**
     * Save the current browser state to a profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveProfile(string $idOrName, array $data = []): Profile
    {
        return Profile::fromArray($this->postJson($this->path('/browsers/%s/profiles', $idOrName), $data));
    }

    /**
     * Upload unpacked extensions (as ZIP archives) to a running browser.
     *
     * @param  array<string, string|resource>  $extensions  Map of extension folder name => ZIP path, resource or contents.
     */
    public function uploadExtensions(string $idOrName, array $extensions): void
    {
        $parts = [];

        // Each extension is sent as an ordered name/zip_file pair.
        foreach ($extensions as $name => $zip) {
            $parts[] = ['name' => 'extensions.name', 'contents' => (string) $name];
            $parts[] = $this->filePart('extensions.zip_file', $zip, "{$name}.zip");
        }

        $this->postMultipart($this->path('/browsers/%s/extensions', $idOrName), $parts);
    }
}
