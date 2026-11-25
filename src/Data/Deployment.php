<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Enums\DeploymentSourceType;
use Nietthijmen\LaravelKernel\Enums\DeploymentStatus;

final readonly class Deployment extends Data
{
    /**
     * @param  DeploymentStatus|null  $status  Null if the API returned a status this package does not know yet (see `$raw['status']`).
     * @param  array<string, string>  $envVars  Values are redacted (empty) for API-key callers.
     */
    public function __construct(
        public string $id,
        public ?DeploymentStatus $status,
        public string $region,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ?string $statusReason,
        public ?string $entrypointRelPath,
        public array $envVars,
        public ?DeploymentSourceType $sourceType,
        public ?string $sourceUrl,
        public ?string $sourceRef,
        public ?string $sourcePath,
        public ?string $sourceChecksum,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            status: DeploymentStatus::tryFrom((string) $data['status']),
            region: (string) $data['region'],
            createdAt: CarbonImmutable::parse($data['created_at']),
            updatedAt: self::date($data['updated_at'] ?? null),
            statusReason: self::string($data['status_reason'] ?? null),
            entrypointRelPath: self::string($data['entrypoint_rel_path'] ?? null),
            envVars: $data['env_vars'] ?? [],
            sourceType: DeploymentSourceType::tryFrom((string) ($data['source_type'] ?? '')),
            sourceUrl: self::string($data['source_url'] ?? null),
            sourceRef: self::string($data['source_ref'] ?? null),
            sourcePath: self::string($data['source_path'] ?? null),
            sourceChecksum: self::string($data['source_checksum'] ?? null),
            raw: $data,
        );
    }
}
