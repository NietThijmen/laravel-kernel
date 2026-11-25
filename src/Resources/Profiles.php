<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Profile;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/profiles
 */
class Profiles extends BaseResource
{
    /**
     * @param  array{query?: string, name?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Profile>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/profiles', Profile::class, $query);
    }

    /**
     * @param  array{query?: string, name?: string}  $query
     * @return LazyCollection<int, Profile>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/profiles', Profile::class, $query);
    }

    /**
     * @param  array<string, mixed>  $data  e.g. ['name' => 'my-profile']
     */
    public function create(array $data = []): Profile
    {
        return Profile::fromArray($this->postJson('/profiles', $data));
    }

    public function get(string $idOrName): Profile
    {
        return Profile::fromArray($this->getJson($this->path('/profiles/%s', $idOrName)));
    }

    /**
     * @param  array<string, mixed>  $data  e.g. ['name' => 'renamed']
     */
    public function update(string $idOrName, array $data): Profile
    {
        return Profile::fromArray($this->patchJson($this->path('/profiles/%s', $idOrName), $data));
    }

    public function delete(string $idOrName): void
    {
        $this->sendDelete($this->path('/profiles/%s', $idOrName));
    }

    /**
     * Download the profile archive and return its raw contents.
     *
     * Returns null when the profile exists but has not captured any state
     * yet (use it in a browser session first).
     *
     * @param  'tar.zst'|'tar'  $format
     */
    public function download(string $idOrName, string $format = 'tar.zst'): ?string
    {
        $response = $this->getRaw($this->path('/profiles/%s/download', $idOrName), ['format' => $format]);

        return $response->status() === 202 ? null : $response->body();
    }
}
