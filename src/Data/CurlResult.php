<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * The result of an HTTP request made through a browser's network stack.
 */
final readonly class CurlResult extends Data
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public int $durationMs,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            status: (int) $data['status'],
            headers: $data['headers'] ?? [],
            body: (string) $data['body'],
            durationMs: (int) $data['duration_ms'],
            raw: $data,
        );
    }

    /**
     * First value of a response header (case-insensitive).
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function json(): mixed
    {
        return json_decode($this->body, true);
    }
}
