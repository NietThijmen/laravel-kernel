<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Deployment;
use Nietthijmen\LaravelKernel\Support\EventStream;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

/**
 * @see https://www.kernel.sh/docs/api-reference/deployments
 */
class Deployments extends BaseResource
{
    /**
     * @param  array{app_name?: string, app_version?: string, query?: string, limit?: int, offset?: int}  $query
     * @return PaginatedResponse<Deployment>
     */
    public function list(array $query = []): PaginatedResponse
    {
        return $this->paginate('/deployments', Deployment::class, $query);
    }

    /**
     * @param  array{app_name?: string, app_version?: string, query?: string}  $query
     * @return LazyCollection<int, Deployment>
     */
    public function all(array $query = []): LazyCollection
    {
        return $this->lazy('/deployments', Deployment::class, $query);
    }

    /**
     * Create a deployment, either from a ZIP file or from a GitHub source.
     *
     * From a ZIP file:
     *   ['file' => '/path/to/app.zip', 'entrypoint_rel_path' => 'src/app.ts']
     *
     * From GitHub:
     *   ['source' => ['type' => 'github', 'url' => '...', 'ref' => 'main', 'entrypoint' => 'src/app.ts']]
     *
     * Optional: env_vars (array), force (bool), region, runtime_policy (array), version.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Deployment
    {
        $parts = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $parts = [...$parts, ...match (true) {
                $key === 'file' => [$this->filePart('file', $value, 'app.zip')],
                in_array($key, ['source', 'runtime_policy'], true) => [[
                    'name' => $key,
                    'contents' => json_encode($value, JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ]],
                $key === 'env_vars' => array_map(
                    fn ($name, $envValue) => ['name' => "env_vars[{$name}]", 'contents' => (string) $envValue],
                    array_keys($value),
                    $value,
                ),
                is_bool($value) => [['name' => $key, 'contents' => $value ? 'true' : 'false']],
                default => [['name' => $key, 'contents' => (string) $value]],
            }];
        }

        return Deployment::fromArray($this->postMultipart('/deployments', $parts));
    }

    public function get(string $id): Deployment
    {
        return Deployment::fromArray($this->getJson($this->path('/deployments/%s', $id)));
    }

    /**
     * Stop a running deployment and mark it for deletion.
     */
    public function delete(string $id): void
    {
        $this->sendDelete($this->path('/deployments/%s', $id));
    }

    /**
     * Stream deployment events: LogEvent, DeploymentStateEvent, AppVersionSummaryEvent and ErrorEvent.
     *
     * @param  string|null  $since  RFC 3339 timestamp or a duration such as "5m".
     */
    public function events(string $id, ?string $since = null, bool $includeHeartbeats = false): EventStream
    {
        return $this->stream($this->path('/deployments/%s/events', $id), $this->filter(['since' => $since]), $includeHeartbeats);
    }
}
