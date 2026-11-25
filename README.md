# A kernel.sh wrapper for laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nietthijmen/laravel-kernel.svg?style=flat-square)](https://packagist.org/packages/nietthijmen/laravel-kernel)
[![GitHub Tests Action Status](https://github.com/nietthijmen/laravel-kernel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/nietthijmen/laravel-kernel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/nietthijmen/laravel-kernel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/nietthijmen/laravel-kernel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nietthijmen/laravel-kernel.svg?style=flat-square)](https://packagist.org/packages/nietthijmen/laravel-kernel)

A Laravel client for the [Kernel](https://www.kernel.sh) API: browsers-as-a-service, app deployments and invocations.

## Installation

```bash
composer require nietthijmen/laravel-kernel
```

Add your API key to `.env`:

```dotenv
KERNEL_API_KEY=your-api-key
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-kernel-config"
```

```php
return [
    'api_key' => env('KERNEL_API_KEY'),
    'base_url' => env('KERNEL_BASE_URL', 'https://api.onkernel.com'),
    'timeout' => (int) env('KERNEL_TIMEOUT', 60),
    'retries' => (int) env('KERNEL_RETRIES', 2),       // retried on connection errors and 429 only
    'retry_delay' => (int) env('KERNEL_RETRY_DELAY', 500), // milliseconds
];
```

## Usage

Use the facade or inject `Nietthijmen\LaravelKernel\LaravelKernel`.

```php
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;
```

### Typed responses

Responses are returned as immutable, typed objects in `Nietthijmen\LaravelKernel\Data` (`App`, `Deployment`, `Invocation`, `Browser`, `BrowserPool`, `Profile`, `Proxy`, `Extension`, ...). Properties are camelCased versions of the [API fields](https://www.kernel.sh/docs/api-reference), dates are `CarbonImmutable` and statuses are enums:

```php
use Nietthijmen\LaravelKernel\Enums\DeploymentStatus;

$deployment = Kernel::deployments()->get('dep_123');

$deployment->status === DeploymentStatus::Running;
$deployment->createdAt->diffForHumans();
```

Every object also keeps the original payload:

```php
$deployment->raw['some_new_field']; // fields not modelled (yet) by this package
$deployment->toArray();             // the exact API payload
json_encode($deployment);           // same
```

If Kernel adds an enum value this package doesn't know yet, the enum property is `null` instead of throwing; the original value is still in `$raw`.

### Pagination

`list()` returns one page. Pagination data comes from the API's `X-Has-More` / `X-Next-Offset` headers:

```php
$page = Kernel::apps()->list(['app_name' => 'my-app', 'limit' => 20]);

foreach ($page as $app) {
    echo $app->appName.'@'.$app->version;
}

$page->hasMore;     // bool
$page->nextOffset;  // ?int
```

`all()` returns a `LazyCollection` that fetches more pages only as you iterate:

```php
Kernel::browsers()->all(['status' => 'active'])->each(fn (Browser $browser) => /* ... */);
```

### Apps, deployments and invocations

```php
// Deploy from a ZIP file...
$deployment = Kernel::deployments()->create([
    'file' => storage_path('app.zip'),
    'entrypoint_rel_path' => 'src/index.ts',
    'env_vars' => ['OPENAI_API_KEY' => '...'],
]);

// ...or from GitHub
Kernel::deployments()->create([
    'source' => ['type' => 'github', 'url' => 'https://github.com/acme/app', 'ref' => 'main', 'entrypoint' => 'src/index.ts'],
]);

// Stream deployment events (Server-Sent Events)
foreach (Kernel::deployments()->events($deployment->id) as $event) {
    match (true) {
        $event instanceof LogEvent => logger($event->message),
        $event instanceof DeploymentStateEvent => logger($event->deployment->status?->value),
        $event instanceof ErrorEvent => throw new RuntimeException($event->message),
        default => null,
    };
}

// Invoke an action
$result = Kernel::invocations()->create('my-app', 'analyze', ['url' => 'https://example.com']);
$result->json(); // decoded output

$result = Kernel::invocations()->create('my-app', 'analyze', ['url' => '...'], ['async' => true]);
$invocation = Kernel::invocations()->get($result->id);
$invocation->status?->isFinished();
```

### Browsers

```php
$browser = Kernel::browsers()->create(['headless' => true, 'timeout_seconds' => 300]);

$browser->cdpWsUrl;    // connect Playwright / Puppeteer
$browser->liveViewUrl; // headful browsers only

Kernel::browsers()->delete($browser->sessionId);
```

### Computer controls

Browsers can be driven with the mouse and keyboard. Actions are chainable, and coordinates are pixels from the top-left of the screen:

```php
$browser = Kernel::browsers()->create();

$browser
    ->click(640, 360)
    ->fill(640, 420, 'me@example.com')   // click field, select all, type
    ->fill(640, 480, 'secret')
    ->press('Return')                     // xdotool key names: Return, Tab, Ctrl+t, ...
    ->pause(1000)
    ->scroll(640, 360, deltaY: 5);

file_put_contents('page.png', $browser->screenshot());
```

Also available: `doubleClick()`, `rightClick()`, `type()`, `moveMouse()`, `drag()`, `hideCursor()` / `showCursor()`, `writeClipboard()`, `readClipboard()`, `mousePosition()` and `computer()->batch([...])` for raw batched actions.

If you only have a session ID, use `Kernel::browsers()->computer('sess_123')->click(10, 20)`. A browser's controls use the client that fetched it, so `Kernel::withApiKey(...)` keeps working.

### Playwright scripts

Build Playwright code with the `Script` builder and run it inside the browser's VM. Selectors are CSS / Playwright selector strings or `Locator`s, and every value is JSON-encoded, so user input can't inject code:

```php
use Nietthijmen\LaravelKernel\Playwright\Locator;
use Nietthijmen\LaravelKernel\Playwright\Script;

$result = $browser->run(
    Script::make()
        ->timeout(5000)                      // per action / assertion, in ms
        ->screenshotOnFailure()
        ->goto('https://app.test/register')
        ->fill(Locator::label('Email'), 'me@example.com')
        ->fill('#password', 'secret')
        ->check(Locator::label('Accept terms'))
        ->select('#country', 'nl')
        ->attach('#avatar', 'avatar.png', file_get_contents($path), 'image/png')
        ->click(Locator::role('button', 'Sign up'))
        ->waitForUrl('**/welcome')
        ->assertPathIs('/welcome')
        ->assertSee('Welcome')
        ->text('h1', as: 'heading')
);

if ($result->failed()) {
    // 'Step 9 (assert see "Welcome") failed: Expected to see "Welcome" on the page'
    logger()->error($result->failureMessage());
    Storage::put('failure.png', $result->failureScreenshot());
}

$result->get('heading');
$result->throw(); // throws ScriptFailedException when a step failed
```

Each step has a label. When a step throws, the rest of the script is skipped, and the result tells you which step failed, why, and what ran before it (`completedSteps()`). Values extracted before the failure are still available.

| | |
|---|---|
| Navigation | `goto`, `reload`, `back`, `waitForUrl`, `waitForLoadState`, `waitFor`, `waitForText`, `pause` |
| Interaction | `click`, `doubleClick`, `hover`, `focus`, `fill`, `clear`, `type`, `press`, `keyboard`, `check`, `uncheck`, `select`, `attach` |
| Assertions (retried until timeout) | `assertSee`, `assertDontSee`, `assertSeeIn`, `assertVisible`, `assertMissing`, `assertPresent`, `assertCount`, `assertInputValue`, `assertSelected`, `assertChecked`, `assertNotChecked`, `assertEnabled`, `assertDisabled`, `assertTitle`, `assertTitleContains`, `assertUrlIs`, `assertPathIs` |
| Extraction | `title`, `url`, `text`, `texts`, `value`, `attribute`, `count`, `isVisible`, `html`, `evaluate`, `screenshot` |
| Custom | `step($label, $js)` / `raw($js)`: use `page` and store values on `results` |

Locators: `Locator::css()`, `label()`, `role()`, `text()`, `placeholder()`, `testId()`, plus `->first()`, `->last()`, `->nth()`, `->withText()` and `->within($parent)`.

`Script` supports `when()` / `unless()` and macros, which makes it easy to build reusable flows:

```php
Script::macro('login', fn (string $email, string $password) => $this
    ->goto('https://app.test/login')
    ->fill(Locator::label('Email'), $email)
    ->fill(Locator::label('Password'), $password)
    ->click(Locator::role('button', 'Log in'))
    ->waitForUrl('**/dashboard'));

$browser->run(Script::make()->login('me@example.com', 'secret')->assertSee('Dashboard'));
```

Raw Playwright code and executors are also available:

```php
$browser->execute('await page.goto("https://example.com"); return await page.title();')->result;

// Named executors get their own tab and run concurrently (max 8 per browser)
$browser->run($checkoutScript, executor: 'checkout', timeout: 120);
$browser->playwright()->executors();
$browser->playwright()->deleteExecutor('checkout');

Kernel::browsers()->playwright('sess_123')->run($script); // with only a session ID
```

Script execution is never retried automatically, so a form is never submitted twice.

### Browser pools, profiles, proxies and extensions

```php
Kernel::browserPools()->create(['name' => 'scrapers', 'size' => 5]);
$browser = Kernel::browserPools()->acquire('scrapers', ['acquire_timeout_seconds' => 30]); // null on timeout
Kernel::browserPools()->release('scrapers', $browser->sessionId);

Kernel::profiles()->create(['name' => 'logged-in']);
$archive = Kernel::profiles()->download('logged-in'); // null if the profile has no state yet

Kernel::proxies()->create(['type' => 'residential', 'name' => 'us-resi']);

Kernel::extensions()->upload('/path/to/extension.zip', name: 'ublock');
```

### Other endpoints

Endpoints without a dedicated method can be called directly. Errors are still turned into exceptions:

```php
$context = Kernel::request('GET', '/auth/context')->json();
```

Use `Kernel::withApiKey('...')` to call the API with a different key, for example per tenant.

### Errors

Failed requests throw `Nietthijmen\LaravelKernel\Exceptions\KernelException` (or one of its subclasses: `AuthenticationException`, `NotFoundException`, `ValidationException`, `RateLimitException`):

```php
try {
    Kernel::deployments()->get('dep_123');
} catch (KernelException $e) {
    $e->status;     // 404
    $e->errorCode;  // "not_found"
    $e->getMessage();
    $e->details;
}
```

### Artisan

```bash
php artisan kernel:apps --app=my-app
```

### Testing your app

The client uses Laravel's HTTP client, so you can use `Http::fake()`:

```php
Http::fake(['api.onkernel.com/apps*' => Http::response([...], 200, ['X-Has-More' => 'false'])]);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [nietthijmen](https://github.com/NietThijmen)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
