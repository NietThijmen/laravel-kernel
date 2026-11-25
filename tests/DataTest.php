<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\BrowserPool;
use Nietthijmen\LaravelKernel\Data\Deployment;
use Nietthijmen\LaravelKernel\Data\Events\ErrorEvent;
use Nietthijmen\LaravelKernel\Data\Events\Event;
use Nietthijmen\LaravelKernel\Data\Events\InvocationStateEvent;
use Nietthijmen\LaravelKernel\Data\Events\UnknownEvent;
use Nietthijmen\LaravelKernel\Data\Invocation;
use Nietthijmen\LaravelKernel\Data\Profile;
use Nietthijmen\LaravelKernel\Data\Proxy;
use Nietthijmen\LaravelKernel\Enums\DeploymentStatus;
use Nietthijmen\LaravelKernel\Enums\InvocationStatus;
use Nietthijmen\LaravelKernel\Enums\ProxyType;
use Nietthijmen\LaravelKernel\Enums\Region;
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;

beforeEach(fn () => Http::preventStrayRequests());

function browser_(array $overrides = []): array
{
    return array_replace([
        'session_id' => 'sess_1',
        'name' => 'scraper',
        'cdp_ws_url' => 'wss://cdp.example/sess_1',
        'webdriver_ws_url' => 'wss://wd.example/sess_1',
        'browser_live_view_url' => 'https://live.example/sess_1',
        'created_at' => '2026-11-25T10:00:00Z',
        'region' => 'eu-west',
        'headless' => false,
        'stealth' => false,
        'captcha_solver' => ['enabled' => true],
        'memory' => '8GiB',
        'timeout_seconds' => 600,
        'viewport' => ['width' => 1920, 'height' => 1080, 'refresh_rate' => 60],
        'profile' => ['id' => 'prof_1', 'name' => 'logged-in', 'created_at' => '2026-11-01T00:00:00Z'],
        'pool' => ['id' => 'pool_1', 'name' => 'scrapers'],
        'replay' => ['replay_id' => 'rep_1', 'started_at' => '2026-11-25T10:00:01Z', 'finished_at' => null],
        'tags' => ['team' => 'backend'],
        'vaults' => [['name' => 'main']],
        'platform' => ['os' => 'linux'],
        'usage' => ['uptime_ms' => 1234],
    ], $overrides);
}

it('hydrates a browser with nested objects', function () {
    $browser = Browser::fromArray(browser_());

    expect($browser->sessionId)->toBe('sess_1')
        ->and($browser->region)->toBe(Region::EuWest)
        ->and($browser->createdAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($browser->createdAt->toIso8601ZuluString())->toBe('2026-11-25T10:00:00Z')
        ->and($browser->captchaSolverEnabled)->toBeTrue()
        ->and($browser->viewport->width)->toBe(1920)
        ->and($browser->profile)->toBeInstanceOf(Profile::class)
        ->and($browser->profile->name)->toBe('logged-in')
        ->and($browser->pool->name)->toBe('scrapers')
        ->and($browser->replay->replayId)->toBe('rep_1')
        ->and($browser->replay->finishedAt)->toBeNull()
        ->and($browser->vaults[0]->name)->toBe('main')
        ->and($browser->tags)->toBe(['team' => 'backend'])
        ->and($browser->os)->toBe('linux')
        ->and($browser->uptimeMs)->toBe(1234)
        ->and($browser->deletedAt)->toBeNull()
        ->and($browser->cost)->toBeNull();
});

it('keeps the raw payload for toArray, json and unmodelled fields', function () {
    $payload = browser_(['some_future_field' => 'x']);
    $browser = Browser::fromArray($payload);

    expect($browser->toArray())->toBe($payload)
        ->and(json_encode($browser))->toBe(json_encode($payload))
        ->and($browser->raw['some_future_field'])->toBe('x');
});

it('maps unknown enum values to null instead of failing', function () {
    $deployment = Deployment::fromArray([
        'id' => 'dep_1',
        'status' => 'hibernating',
        'region' => 'aws.us-east-1a',
        'created_at' => '2026-11-25T10:00:00Z',
    ]);

    expect($deployment->status)->toBeNull()
        ->and($deployment->raw['status'])->toBe('hibernating')
        ->and($deployment->sourceType)->toBeNull()
        ->and($deployment->envVars)->toBe([]);
});

it('exposes status helpers on enums', function () {
    expect(DeploymentStatus::InProgress->isFinished())->toBeFalse()
        ->and(DeploymentStatus::Running->isFinished())->toBeTrue()
        ->and(InvocationStatus::Running->isFinished())->toBeFalse()
        ->and(InvocationStatus::Succeeded->isFinished())->toBeTrue();
});

it('decodes invocation output', function () {
    $invocation = Invocation::fromArray([
        'id' => 'inv_1',
        'app_name' => 'my-app',
        'action_name' => 'run',
        'version' => 'latest',
        'status' => 'succeeded',
        'output' => '{"title":"Example"}',
        'started_at' => '2026-11-25T10:00:00Z',
        'finished_at' => '2026-11-25T10:00:05Z',
    ]);

    expect($invocation->status)->toBe(InvocationStatus::Succeeded)
        ->and($invocation->json())->toBe(['title' => 'Example'])
        ->and($invocation->finishedAt->diffInSeconds($invocation->startedAt, true))->toBe(5.0);
});

it('returns typed browser pools and acquired browsers', function () {
    Http::fake([
        'api.onkernel.com/browser_pools/scrapers/acquire' => Http::response(browser_()),
        'api.onkernel.com/browser_pools/scrapers' => Http::response([
            'id' => 'pool_1',
            'name' => 'scrapers',
            'region' => 'us-east',
            'available_count' => 3,
            'acquired_count' => 2,
            'created_at' => '2026-11-25T10:00:00Z',
            'extension_ids' => ['ext_1'],
            'browser_pool_config' => [
                'size' => 5,
                'headless' => true,
                'extensions' => [['name' => 'ublock']],
                'profile' => ['name' => 'logged-in'],
            ],
        ]),
    ]);

    $pool = Kernel::browserPools()->get('scrapers');

    expect($pool)->toBeInstanceOf(BrowserPool::class)
        ->and($pool->region)->toBe(Region::UsEast)
        ->and($pool->config->size)->toBe(5)
        ->and($pool->config->headless)->toBeTrue()
        ->and($pool->config->extensions[0]->name)->toBe('ublock')
        ->and($pool->config->profile->name)->toBe('logged-in')
        ->and($pool->config->viewport)->toBeNull();

    expect(Kernel::browserPools()->acquire('scrapers'))->toBeInstanceOf(Browser::class);
});

it('returns typed proxies', function () {
    Http::fake(['*' => Http::response([
        ['id' => 'prx_1', 'name' => 'us-resi', 'type' => 'residential', 'status' => 'available', 'config' => ['country' => 'US']],
    ], 200, ['X-Has-More' => 'false'])]);

    $proxy = Kernel::proxies()->list()->items[0];

    expect($proxy)->toBeInstanceOf(Proxy::class)
        ->and($proxy->type)->toBe(ProxyType::Residential)
        ->and($proxy->config)->toBe(['country' => 'US'])
        ->and($proxy->bypassHosts)->toBe([]);
});

it('returns invocation browsers as a list of Browser objects', function () {
    Http::fake(['*' => Http::response(['browsers' => [browser_(), browser_(['session_id' => 'sess_2'])]])]);

    $browsers = Kernel::invocations()->browsers('inv_1');

    expect($browsers)->toHaveCount(2)
        ->and($browsers[1]->sessionId)->toBe('sess_2');
});

it('returns null when downloading a profile without state', function () {
    Http::fake(['*' => Http::response(null, 202)]);

    expect(Kernel::profiles()->download('empty'))->toBeNull();
});

it('builds typed events', function () {
    $state = Event::make([
        'event' => 'invocation_state',
        'timestamp' => '2026-11-25T10:00:00Z',
        'invocation' => [
            'id' => 'inv_1', 'app_name' => 'a', 'action_name' => 'run', 'version' => 'latest',
            'status' => 'running', 'started_at' => '2026-11-25T10:00:00Z',
        ],
    ]);
    $error = Event::make([
        'event' => 'error',
        'timestamp' => '2026-11-25T10:00:00Z',
        'error' => ['code' => 'build_failed', 'message' => 'npm install failed'],
    ]);
    $unknown = Event::make(['event' => 'something_new', 'foo' => 'bar']);

    expect($state)->toBeInstanceOf(InvocationStateEvent::class)
        ->and($state->invocation->status)->toBe(InvocationStatus::Running)
        ->and($error)->toBeInstanceOf(ErrorEvent::class)
        ->and($error->code)->toBe('build_failed')
        ->and($error->message)->toBe('npm install failed')
        ->and($unknown)->toBeInstanceOf(UnknownEvent::class)
        ->and($unknown->event)->toBe('something_new')
        ->and($unknown->raw['foo'])->toBe('bar');
});
