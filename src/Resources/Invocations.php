<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\Invocation;
use Nietthijmen\LaravelKernel\Data\InvokeResult;
use Nietthijmen\LaravelKernel\Support\EventStream;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/invocations
 */
class Invocations extends BaseResource
{
    /**
     * @param  array{app_name?: string, version?: string, action_name?: string, deployment_id?: string, status?: string, since?: string, query?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Invocation>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/invocations', Invocation::class, $query);
    }

    /**
     * @param  array{app_name?: string, version?: string, action_name?: string, deployment_id?: string, status?: string, since?: string, query?: string}  $query
     * @return LazyCollection<int, Invocation>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/invocations', Invocation::class, $query);
    }

    /**
     * Invoke an action of a deployed app.
     *
     * @param  array<string, mixed>|string|null  $payload  Arrays are JSON-encoded, as the API expects a string payload.
     * @param  array{version?: string, async?: bool, async_timeout_seconds?: int}  $options
     */
    public function create(string $appName, string $actionName, array|string|null $payload = null, array $options = []): InvokeResult
    {
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        return InvokeResult::fromArray($this->postJson('/invocations', $this->filter([
            'app_name' => $appName,
            'action_name' => $actionName,
            'payload' => $payload,
            ...$options,
        ])));
    }

    public function get(string $id): Invocation
    {
        return Invocation::fromArray($this->getJson($this->path('/invocations/%s', $id)));
    }

    /**
     * Update an invocation, e.g. ['status' => 'succeeded', 'output' => '...'].
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Invocation
    {
        return Invocation::fromArray($this->patchJson($this->path('/invocations/%s', $id), $data));
    }

    /**
     * List the browser sessions created by an invocation.
     *
     * @return list<Browser>
     */
    public function browsers(string $id): array
    {
        $response = $this->getJson($this->path('/invocations/%s/browsers', $id));

        return array_map(fn (array $browser) => $this->hydrate(Browser::class, $browser), $response['browsers'] ?? []);
    }

    /**
     * Delete all browser sessions created by an invocation.
     */
    public function deleteBrowsers(string $id): void
    {
        $this->sendDelete($this->path('/invocations/%s/browsers', $id));
    }

    /**
     * Stream invocation events: LogEvent, InvocationStateEvent and ErrorEvent.
     *
     * @param  string|null  $since  RFC 3339 timestamp or a duration such as "5m".
     */
    public function events(string $id, ?string $since = null, bool $includeHeartbeats = false): EventStream
    {
        return $this->stream($this->path('/invocations/%s/events', $id), $this->filter(['since' => $since]), $includeHeartbeats);
    }
}
