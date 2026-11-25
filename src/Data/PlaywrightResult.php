<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * The result of executing Playwright code against a browser.
 */
final readonly class PlaywrightResult extends Data
{
    /**
     * @param  mixed  $result  The value returned by the code.
     * @param  string|null  $tabTargetId  CDP target ID of the tab `page` was bound to.
     * @param  bool|null  $tabCreated  Whether this call opened that tab.
     */
    public function __construct(
        public bool $success,
        public mixed $result,
        public ?string $error,
        public ?string $stdout,
        public ?string $stderr,
        public ?string $tabTargetId,
        public ?bool $tabCreated,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            success: (bool) $data['success'],
            result: $data['result'] ?? null,
            error: self::string($data['error'] ?? null),
            stdout: self::string($data['stdout'] ?? null),
            stderr: self::string($data['stderr'] ?? null),
            tabTargetId: self::string($data['tab']['target_id'] ?? null),
            tabCreated: self::bool($data['tab']['created'] ?? null),
            raw: $data,
        );
    }
}
