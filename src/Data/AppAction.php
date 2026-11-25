<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * An action exposed by a deployed app version.
 */
final readonly class AppAction extends Data
{
    /**
     * @param  array<string, mixed>|null  $inputSchema  JSON Schema (draft-07), null if not generated.
     * @param  array<string, mixed>|null  $outputSchema  JSON Schema (draft-07), null if not generated.
     */
    public function __construct(
        public string $name,
        public ?array $inputSchema,
        public ?array $outputSchema,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            name: (string) $data['name'],
            inputSchema: $data['input_schema'] ?? null,
            outputSchema: $data['output_schema'] ?? null,
            raw: $data,
        );
    }
}
