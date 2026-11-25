<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * An app version, as returned by `GET /apps`.
 */
final readonly class App extends Data
{
    /**
     * @param  list<AppAction>  $actions
     * @param  array<string, string>  $envVars  Values are redacted (empty) for API-key callers.
     */
    public function __construct(
        public string $id,
        public string $appName,
        public string $version,
        public string $region,
        public string $deploymentId,
        public array $actions,
        public array $envVars,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: (string) $data['id'],
            appName: (string) $data['app_name'],
            version: (string) $data['version'],
            region: (string) $data['region'],
            deploymentId: (string) $data['deployment'],
            actions: self::many($data['actions'] ?? [], AppAction::class),
            envVars: $data['env_vars'] ?? [],
            raw: $data,
        );
    }

    public function action(string $name): ?AppAction
    {
        foreach ($this->actions as $action) {
            if ($action->name === $name) {
                return $action;
            }
        }

        return null;
    }
}
