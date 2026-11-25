<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\App;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/apps/list-apps
 */
class Apps extends BaseResource
{
    /**
     * List app versions.
     *
     * @param  array{app_name?: string, version?: string, query?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<App>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/apps', App::class, $query);
    }

    /**
     * Lazily iterate over every app version, across all pages.
     *
     * @param  array{app_name?: string, version?: string, query?: string}  $query
     * @return LazyCollection<int, App>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/apps', App::class, $query);
    }
}
