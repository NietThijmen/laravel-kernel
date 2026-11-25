<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Http\Client\Response;
use Illuminate\Support\LazyCollection;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\Data;
use Nietthijmen\LaravelKernel\LaravelKernel;
use Nietthijmen\LaravelKernel\Support\ClientBinding;
use Nietthijmen\LaravelKernel\Support\EventStream;
use Nietthijmen\LaravelKernel\Support\PaginatedResponse;

abstract class BaseResource
{
    public function __construct(protected LaravelKernel $kernel) {}

    /**
     * Build a path, URL-encoding each dynamic segment.
     */
    protected function path(string $template, string ...$segments): string
    {
        return vsprintf($template, array_map('rawurlencode', $segments));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function getJson(string $uri, array $query = []): mixed
    {
        return $this->kernel->request('GET', $uri, $query)->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function postJson(string $uri, array $data = []): mixed
    {
        return $this->kernel->request('POST', $uri, json: $data === [] ? null : $data)->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function patchJson(string $uri, array $data = []): mixed
    {
        return $this->kernel->request('PATCH', $uri, json: $data)->json();
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function sendDelete(string $uri, ?array $data = null): void
    {
        $this->kernel->request('DELETE', $uri, json: $data);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function getRaw(string $uri, array $query = []): Response
    {
        return $this->kernel->request('GET', $uri, $query);
    }

    /**
     * Fetch a single page from a list endpoint and map each item to a DTO.
     *
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $query
     * @return PaginatedResponse<T>
     */
    protected function paginate(string $uri, string $class, array $query = []): PaginatedResponse
    {
        return PaginatedResponse::fromResponse(
            $this->kernel->request('GET', $uri, $query),
            fn (array $item) => $this->hydrate($class, $item),
        );
    }

    /**
     * Build a DTO from an API payload. Browsers are bound to this client so
     * their computer controls use the same API key.
     *
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     */
    protected function hydrate(string $class, array $data): Data
    {
        $object = $class::fromArray($data);

        if ($object instanceof Browser) {
            ClientBinding::bind($object, $this->kernel);
        }

        return $object;
    }

    /**
     * Lazily iterate over every item of a list endpoint, fetching
     * additional pages only when needed.
     *
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $query
     * @return LazyCollection<int, T>
     */
    protected function lazy(string $uri, string $class, array $query = []): LazyCollection
    {
        return LazyCollection::make(function () use ($uri, $class, $query) {
            $query['limit'] ??= 100;
            $query['offset'] ??= 0;

            do {
                $page = $this->paginate($uri, $class, $query);

                // Plain `yield` (not `yield from`) so keys keep incrementing
                // across pages instead of restarting at 0 for every page.
                foreach ($page->items as $item) {
                    yield $item;
                }

                $query['offset'] = $page->nextOffset ?: ($query['offset'] + count($page->items));
            } while ($page->hasMore && count($page->items) > 0);
        });
    }

    /**
     * Open a Server-Sent Events stream.
     *
     * @param  array<string, mixed>  $query
     */
    protected function stream(string $uri, array $query = [], bool $includeHeartbeats = false): EventStream
    {
        $response = $this->kernel->ensureSuccessful(
            $this->kernel->http()
                ->timeout(0)
                ->accept('text/event-stream')
                ->withOptions(['stream' => true])
                ->get($uri, $query)
        );

        return new EventStream($response->toPsrResponse()->getBody(), $includeHeartbeats);
    }

    /**
     * Remove null values so optional arguments are not sent to the API.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function filter(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== null);
    }

    /**
     * Send a multipart/form-data POST request.
     *
     * @param  array<int, array{name: string, contents: mixed, filename?: string, headers?: array<string, string>}>  $parts
     */
    protected function postMultipart(string $uri, array $parts): mixed
    {
        return $this->kernel->ensureSuccessful(
            $this->kernel->http()->asMultipart()->post($uri, $parts)
        )->json();
    }

    /**
     * Build a multipart file part from a file path, resource or raw contents.
     *
     * @param  string|resource  $file
     * @return array{name: string, contents: mixed, filename: string}
     */
    protected function filePart(string $name, mixed $file, string $defaultFilename): array
    {
        if (is_string($file) && is_file($file)) {
            return ['name' => $name, 'contents' => fopen($file, 'r'), 'filename' => basename($file)];
        }

        return ['name' => $name, 'contents' => $file, 'filename' => $defaultFilename];
    }
}
