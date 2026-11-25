<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Extension;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/extensions
 */
class Extensions extends BaseResource
{
    /**
     * @param  array{query?: string, name?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Extension>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/extensions', Extension::class, $query);
    }

    /**
     * @param  array{query?: string, name?: string}  $query
     * @return LazyCollection<int, Extension>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/extensions', Extension::class, $query);
    }

    /**
     * Upload an extension ZIP.
     *
     * @param  string|resource  $zip  Path to a ZIP file, a stream resource or raw ZIP contents.
     */
    public function upload(mixed $zip, ?string $name = null): Extension
    {
        $parts = [$this->filePart('file', $zip, 'extension.zip')];

        if ($name !== null) {
            $parts[] = ['name' => 'name', 'contents' => $name];
        }

        return Extension::fromArray($this->postMultipart('/extensions', $parts));
    }

    /**
     * Download the extension archive and return its raw contents.
     */
    public function download(string $idOrName): string
    {
        return $this->getRaw($this->path('/extensions/%s', $idOrName))->body();
    }

    public function get(string $idOrName): Extension
    {
        return Extension::fromArray($this->getJson($this->path('/extensions/%s/metadata', $idOrName)));
    }

    public function delete(string $idOrName): void
    {
        $this->sendDelete($this->path('/extensions/%s', $idOrName));
    }

    /**
     * Download an extension from the Chrome Web Store and return the raw ZIP contents.
     *
     * @param  'win'|'mac'|'linux'|null  $os
     */
    public function fromChromeStore(string $url, ?string $os = null): string
    {
        return $this->getRaw('/extensions/from_chrome_store', $this->filter(['url' => $url, 'os' => $os]))->body();
    }
}
