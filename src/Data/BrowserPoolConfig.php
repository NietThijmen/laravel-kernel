<?php

namespace Nietthijmen\LaravelKernel\Data;

/**
 * The configuration browsers in a pool are created with.
 */
final readonly class BrowserPoolConfig extends Data
{
    /**
     * @param  list<Reference>  $extensions
     * @param  array<string, mixed>|null  $chromePolicy
     * @param  array<string, mixed>|null  $network
     * @param  array<string, mixed>|null  $telemetry
     */
    public function __construct(
        public int $size,
        public ?string $name,
        public ?bool $headless,
        public ?bool $kioskMode,
        public ?string $memory,
        public ?int $timeoutSeconds,
        public ?int $fillRatePerMinute,
        public ?string $proxyId,
        public ?string $startUrl,
        public ?bool $startReplay,
        public ?bool $captchaSolverEnabled,
        public ?Reference $profile,
        public ?bool $refreshOnProfileUpdate,
        public array $extensions,
        public ?Viewport $viewport,
        public ?array $chromePolicy,
        public ?array $network,
        public ?array $telemetry,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            size: (int) $data['size'],
            name: self::string($data['name'] ?? null),
            headless: self::bool($data['headless'] ?? null),
            kioskMode: self::bool($data['kiosk_mode'] ?? null),
            memory: self::string($data['memory'] ?? null),
            timeoutSeconds: self::int($data['timeout_seconds'] ?? null),
            fillRatePerMinute: self::int($data['fill_rate_per_minute'] ?? null),
            proxyId: self::string($data['proxy_id'] ?? null),
            startUrl: self::string($data['start_url'] ?? null),
            startReplay: self::bool($data['start_replay'] ?? null),
            captchaSolverEnabled: self::bool($data['captcha_solver']['enabled'] ?? null),
            profile: self::one($data['profile'] ?? null, Reference::class),
            refreshOnProfileUpdate: self::bool($data['refresh_on_profile_update'] ?? null),
            extensions: self::many($data['extensions'] ?? [], Reference::class),
            viewport: self::one($data['viewport'] ?? null, Viewport::class),
            chromePolicy: $data['chrome_policy'] ?? null,
            network: $data['network'] ?? null,
            telemetry: $data['telemetry'] ?? null,
            raw: $data,
        );
    }
}
