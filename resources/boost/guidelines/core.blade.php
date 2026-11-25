## Laravel Kernel (nietthijmen/laravel-kernel)

This package is a Laravel client for the Kernel API (kernel.sh): cloud browsers, browser pools, profiles, proxies, extensions, app deployments and invocations. Use it instead of calling `api.onkernel.com` with `Http::` directly.

### Setup

- The API key is read from `KERNEL_API_KEY` (config: `config/kernel.php`, publish with `php artisan vendor:publish --tag="laravel-kernel-config"`).
- Use the `Nietthijmen\LaravelKernel\Facades\LaravelKernel` facade (commonly aliased as `Kernel`) or inject `Nietthijmen\LaravelKernel\LaravelKernel`.
- Resources: `apps()`, `deployments()`, `invocations()`, `browsers()`, `browserPools()`, `profiles()`, `proxies()`, `extensions()`.
- Use `Kernel::withApiKey($key)` for per-tenant keys; it returns a copy and does not change the default client.

### Responses

- Responses are immutable typed objects in `Nietthijmen\LaravelKernel\Data` with camelCased properties (`$browser->sessionId`, `$browser->cdpWsUrl`). Dates are `CarbonImmutable`, statuses are enums in `Nietthijmen\LaravelKernel\Enums`.
- Enum properties are `null` when the API returns an unknown value; the original payload is always in `$object->raw` / `toArray()`.
- Request payloads (arrays passed to `create()`, `list()`, etc.) use the API's snake_case field names.
- `list()` returns one page (`hasMore`, `nextOffset`). Prefer `all()`, which returns a `LazyCollection` that fetches pages as you iterate.

@verbatim
<code-snippet name="Create a browser and list resources" lang="php">
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;

$browser = Kernel::browsers()->create(['headless' => true, 'timeout_seconds' => 300]);

Kernel::browsers()->all(['status' => 'active'])->each(fn ($browser) => logger($browser->sessionId));

Kernel::browsers()->delete($browser->sessionId);
</code-snippet>
@endverbatim

### Playwright scripts

- Prefer the `Nietthijmen\LaravelKernel\Playwright\Script` builder over hand-written JavaScript. Values are JSON-encoded, so never concatenate user input into raw JS passed to `execute()` / `raw()` / `step()`.
- Target elements with `Nietthijmen\LaravelKernel\Playwright\Locator` (`label()`, `role()`, `text()`, `placeholder()`, `testId()`, `css()`) rather than brittle CSS where possible.
- `run()` returns a `ScriptResult`: check `failed()` / `failureMessage()` / `failureScreenshot()`, read extracted values with `get($name)`, or call `throw()` to raise `ScriptFailedException`.
- Scripts are never retried automatically. Do not wrap them in retry logic that could submit a form twice.
- Reusable flows belong in `Script::macro(...)`.

@verbatim
<code-snippet name="Run a Playwright script in a Kernel browser" lang="php">
use Nietthijmen\LaravelKernel\Playwright\Locator;
use Nietthijmen\LaravelKernel\Playwright\Script;

$result = $browser->run(
    Script::make()
        ->timeout(5000)
        ->screenshotOnFailure()
        ->goto('https://app.test/login')
        ->fill(Locator::label('Email'), $email)
        ->fill(Locator::label('Password'), $password)
        ->click(Locator::role('button', 'Log in'))
        ->waitForUrl('**/dashboard')
        ->assertSee('Dashboard')
        ->text('h1', as: 'heading')
);

if ($result->failed()) {
    logger()->error($result->failureMessage());
}

$heading = $result->get('heading');
</code-snippet>
@endverbatim

### Computer controls

- Browsers can be driven by mouse and keyboard with chainable pixel-coordinate actions: `click()`, `fill()`, `type()`, `press()` (xdotool key names like `Return`, `Ctrl+t`), `scroll()`, `drag()`, `pause()`, `screenshot()`.
- With only a session ID, use `Kernel::browsers()->computer($sessionId)` or `Kernel::browsers()->playwright($sessionId)`.
- Prefer Playwright scripts for DOM interaction; use computer controls for things outside the DOM or when visual interaction is required.

### Deployments and invocations

- `Kernel::deployments()->create([...])` accepts a local ZIP (`file`) or a GitHub `source`. Stream progress with `Kernel::deployments()->events($id)`, which yields `LogEvent`, `DeploymentStateEvent`, `ErrorEvent`, etc. from `Nietthijmen\LaravelKernel\Data\Events`.
- `Kernel::invocations()->create($app, $action, $payload)` invokes an action; pass `['async' => true]` as the fourth argument for async invocations, then poll `Kernel::invocations()->get($id)`.

### Errors and testing

- Failed requests throw `Nietthijmen\LaravelKernel\Exceptions\KernelException` or a subclass (`AuthenticationException`, `NotFoundException`, `ValidationException`, `RateLimitException`), exposing `$status`, `$errorCode` and `$details`.
- `browserPools()->acquire()` and `profiles()->download()` return `null` instead of throwing on timeout / missing state; handle that case.
- Endpoints without a dedicated method: `Kernel::request('GET', '/path')`.
- The client uses Laravel's HTTP client, so fake it in tests with `Http::fake(['api.onkernel.com/*' => ...])`. Never hit the real Kernel API in tests.
