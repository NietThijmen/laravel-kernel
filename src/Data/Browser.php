<?php

namespace Nietthijmen\LaravelKernel\Data;

use Carbon\CarbonImmutable;
use Nietthijmen\LaravelKernel\Concerns\ControlsComputer;
use Nietthijmen\LaravelKernel\Concerns\RunsPlaywright;
use Nietthijmen\LaravelKernel\Enums\Region;

/**
 * A browser session.
 *
 * Besides its data, a browser exposes chainable computer controls:
 *
 *   $browser->click(640, 400)->fill(640, 480, 'me@example.com')->press('Return');
 *
 * and can run Playwright scripts:
 *
 *   $browser->run(Script::make()->goto('https://example.com')->assertSee('Example'));
 */
final readonly class Browser extends Data
{
    use ControlsComputer;
    use RunsPlaywright;

    /**
     * @param  Region|null  $region  Null if the API returned a region this package does not know yet.
     * @param  string  $memory  e.g. "8GiB".
     * @param  array<string, string>  $tags
     * @param  list<Reference>  $vaults
     * @param  array<string, mixed>|null  $chromePolicy
     * @param  array<string, mixed>|null  $proxy  {id?, name?, mode?}
     * @param  array<string, mixed>|null  $network
     * @param  array<string, mixed>|null  $location  {timezone, locale, timezone_source, locale_source}
     * @param  array<string, mixed>|null  $telemetry
     */
    public function __construct(
        public string $sessionId,
        public ?string $name,
        public string $cdpWsUrl,
        public string $webdriverWsUrl,
        public ?string $baseUrl,
        public ?string $liveViewUrl,
        public ?Region $region,
        public bool $headless,
        public ?bool $kioskMode,
        public ?bool $gpu,
        public string $memory,
        public ?string $videoMemory,
        public int $timeoutSeconds,
        public bool $captchaSolverEnabled,
        public ?string $startUrl,
        public ?Viewport $viewport,
        public ?Profile $profile,
        public ?bool $profileSaveChanges,
        public ?Reference $pool,
        public ?BrowserReplay $replay,
        public array $tags,
        public array $vaults,
        public ?string $os,
        public ?string $fingerprintOs,
        public ?int $uptimeMs,
        public ?string $usageStatus,
        public ?BrowserCost $cost,
        public ?array $chromePolicy,
        public ?array $proxy,
        public ?array $network,
        public ?array $location,
        public ?array $telemetry,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $leasedAt,
        public ?CarbonImmutable $deletedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            sessionId: (string) $data['session_id'],
            name: self::string($data['name'] ?? null),
            cdpWsUrl: (string) $data['cdp_ws_url'],
            webdriverWsUrl: (string) $data['webdriver_ws_url'],
            baseUrl: self::string($data['base_url'] ?? null),
            liveViewUrl: self::string($data['browser_live_view_url'] ?? null),
            region: Region::tryFrom((string) $data['region']),
            headless: (bool) $data['headless'],
            kioskMode: self::bool($data['kiosk_mode'] ?? null),
            gpu: self::bool($data['gpu'] ?? null),
            memory: (string) $data['memory'],
            videoMemory: self::string($data['video_memory'] ?? null),
            timeoutSeconds: (int) $data['timeout_seconds'],
            captchaSolverEnabled: (bool) ($data['captcha_solver']['enabled'] ?? false),
            startUrl: self::string($data['start_url'] ?? null),
            viewport: self::one($data['viewport'] ?? null, Viewport::class),
            profile: self::one($data['profile'] ?? null, Profile::class),
            profileSaveChanges: self::bool($data['profile_save_changes'] ?? null),
            pool: self::one($data['pool'] ?? null, Reference::class),
            replay: self::one($data['replay'] ?? null, BrowserReplay::class),
            tags: $data['tags'] ?? [],
            vaults: self::many($data['vaults'] ?? [], Reference::class),
            os: self::string($data['platform']['os'] ?? null),
            fingerprintOs: self::string($data['fingerprint']['os'] ?? null),
            uptimeMs: self::int($data['usage']['uptime_ms'] ?? null),
            usageStatus: self::string($data['usage_status'] ?? null),
            cost: self::one($data['cost'] ?? null, BrowserCost::class),
            chromePolicy: $data['chrome_policy'] ?? null,
            proxy: $data['proxy'] ?? null,
            network: $data['network'] ?? null,
            location: $data['location'] ?? null,
            telemetry: $data['telemetry'] ?? null,
            createdAt: CarbonImmutable::parse($data['created_at']),
            leasedAt: self::date($data['leased_at'] ?? null),
            deletedAt: self::date($data['deleted_at'] ?? null),
            raw: $data,
        );
    }
}
