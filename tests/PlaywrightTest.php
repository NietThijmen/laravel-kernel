<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Data\PlaywrightExecutor;
use Nietthijmen\LaravelKernel\Exceptions\RateLimitException;
use Nietthijmen\LaravelKernel\Exceptions\ScriptFailedException;
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;
use Nietthijmen\LaravelKernel\LaravelKernel;
use Nietthijmen\LaravelKernel\Playwright\Locator;
use Nietthijmen\LaravelKernel\Playwright\Script;

beforeEach(fn () => Http::preventStrayRequests());

function signupScript_(): Script
{
    return Script::make()
        ->timeout(3000)
        ->screenshotOnFailure()
        ->goto('https://app.test/register')
        ->fill(Locator::label('Email'), 'me@example.com')
        ->check('#terms')
        ->select('#country', 'nl')
        ->attach('#avatar', 'avatar.png', 'PNG', 'image/png')
        ->click(Locator::role('button', 'Sign up'))
        ->waitForUrl('**/welcome')
        ->assertSee('Welcome')
        ->text('h1', as: 'heading');
}

it('builds locators', function () {
    expect(Locator::from('#email')->toJs())->toBe('page.locator("#email")')
        ->and(Locator::label('Email')->toJs())->toBe('page.getByLabel("Email", { exact: false })')
        ->and(Locator::role('button', 'Save', exact: true)->toJs())->toBe('page.getByRole("button", { name: "Save", exact: true })')
        ->and(Locator::role('checkbox')->toJs())->toBe('page.getByRole("checkbox")')
        ->and(Locator::testId('submit')->first()->toJs())->toBe('page.getByTestId("submit").first()')
        ->and(Locator::css('li')->withText('Two')->nth(0)->toJs())->toBe('page.locator("li").filter({ hasText: "Two" }).nth(0)')
        ->and(Locator::role('button', 'Save')->within('form#signup')->toJs())->toBe('page.locator("form#signup").getByRole("button", { name: "Save", exact: false })')
        ->and(Locator::role('button', 'Save')->describe())->toBe('button "Save"');
});

it('generates labelled steps', function () {
    expect(signupScript_()->labels())->toBe([
        'go to https://app.test/register',
        'fill label "Email"',
        'check #terms',
        'select #country',
        'attach avatar.png to #avatar',
        'click button "Sign up"',
        'wait for url **/welcome',
        'assert see "Welcome"',
        'get text of h1',
    ]);

    $js = signupScript_()->toJs();

    expect($js)
        ->toContain('const __timeout = 3000;')
        ->toContain('await page.getByLabel("Email", { exact: false }).fill("me@example.com", { timeout: __timeout });')
        ->toContain('buffer: Buffer.from("'.base64_encode('PNG').'", \'base64\')')
        ->toContain('__failure.screenshot')
        ->toContain('results["heading"] = await page.locator("h1").first().innerText({ timeout: __timeout });')
        ->toEndWith('return { results, failure: __failure, steps: __labels.length };');
});

it('encodes user input so it cannot inject code', function () {
    $js = Script::make()->fill('#name', '"); process.exit(1); ("')->toJs();

    expect($js)->toContain('.fill("\"); process.exit(1); (\"", { timeout: __timeout })');
});

it('supports conditionals and macros', function () {
    Script::macro('login', fn (string $email) => $this->fill('#email', $email)->click('#login'));

    $script = Script::make()
        ->login('me@example.com')
        ->when(false, fn (Script $script) => $script->click('#never'))
        ->unless(false, fn (Script $script) => $script->click('#always'));

    expect($script->labels())->toBe(['fill #email', 'click #login', 'click #always']);
});

it('generates syntactically valid javascript', function () {
    $script = signupScript_()
        ->type('#search', 'php', delay: 10)
        ->press('#search', 'Enter')
        ->keyboard('Escape')
        ->hover('nav')->focus('#email')->clear('#email')->doubleClick('.row')->uncheck('#terms')
        ->reload()->back()->pause(10)->waitFor('#done', 'attached')->waitForText('Saved')->waitForLoadState('networkidle')
        ->assertDontSee('Error')->assertSeeIn('#flash', 'Saved')->assertVisible('#flash')->assertMissing('#modal')
        ->assertPresent('form')->assertCount('li', 3)->assertInputValue('#email', 'x')->assertSelected('#country', 'nl')
        ->assertChecked('#a')->assertNotChecked('#b')->assertEnabled('#c')->assertDisabled('#d')
        ->assertTitle('Home')->assertTitleContains('Ho')->assertUrlIs('https://app.test/')->assertPathIs('/')
        ->title()->url()->texts('li', as: 'items')->value('#email', as: 'email')->attribute('a', 'href', as: 'href')
        ->count('li', as: 'n')->isVisible('#x', as: 'visible')->html()->html('#x', as: 'x')
        ->evaluate('() => 1 + 1', as: 'sum')->screenshot(fullPage: true)
        ->raw('results.custom = 1;');

    $check = Process::input($script->toJs())->run([
        'node', '-e', 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{new (Object.getPrototypeOf(async function(){}).constructor)("page","context","browser",s)})',
    ]);

    expect($check->successful())->toBeTrue($check->errorOutput());
})->skip(fn () => ! Process::run('node --version')->successful(), 'Node.js is not installed');

it('runs a script and reports success', function () {
    Http::fake(['*' => Http::response([
        'success' => true,
        'result' => ['results' => ['heading' => 'Welcome', 'shot' => base64_encode('PNG')], 'failure' => null, 'steps' => 9],
        'stdout' => '',
        'tab' => ['target_id' => 'T1', 'created' => false],
    ])]);

    $result = Kernel::browsers()->playwright('sess_1')->run(signupScript_(), executor: 'signup', timeout: 120);

    expect($result->passed())->toBeTrue()
        ->and($result->get('heading'))->toBe('Welcome')
        ->and($result->screenshot('shot'))->toBe('PNG')
        ->and($result->completedSteps())->toHaveCount(9)
        ->and($result->failureMessage())->toBeNull()
        ->and($result->execution->tabTargetId)->toBe('T1')
        ->and($result->throw())->toBe($result);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onkernel.com/browsers/sess_1/playwright/execute'
        && $request['executor'] === 'signup'
        && $request['timeout_sec'] === 120
        && $request['code'] === signupScript_()->toJs());
});

it('reports the failing step', function () {
    Http::fake(['*' => Http::response([
        'success' => true,
        'result' => [
            'results' => ['heading' => 'Create account'],
            'failure' => ['step' => 7, 'label' => 'assert see "Welcome"', 'message' => 'Expected to see "Welcome" on the page', 'screenshot' => base64_encode('FAILPNG')],
            'steps' => 9,
        ],
    ])]);

    $result = Kernel::browsers()->playwright('sess_1')->run(signupScript_());

    expect($result->failed())->toBeTrue()
        ->and($result->failedStep)->toBe(7)
        ->and($result->failureMessage())->toBe('Step 8 (assert see "Welcome") failed: Expected to see "Welcome" on the page')
        ->and($result->failureScreenshot())->toBe('FAILPNG')
        ->and($result->get('heading'))->toBe('Create account')
        ->and($result->completedSteps())->toHaveCount(7)
        ->and(fn () => $result->throw())->toThrow(ScriptFailedException::class, 'Step 8 (assert see "Welcome") failed');
});

it('reports execution errors', function () {
    Http::fake(['*' => Http::response(['success' => false, 'error' => 'Execution timed out after 60s'])]);

    $result = Kernel::browsers()->playwright('sess_1')->run(signupScript_());

    expect($result->failed())->toBeTrue()
        ->and($result->failedStep)->toBeNull()
        ->and($result->failureMessage())->toBe('Execution timed out after 60s')
        ->and($result->completedSteps())->toBe([]);
});

it('executes raw code without retrying', function () {
    config()->set('kernel.retries', 3);
    app()->forgetInstance(LaravelKernel::class);
    Kernel::clearResolvedInstances();

    Http::fake(['*' => Http::response(['code' => 'rate_limited', 'message' => 'Slow down'], 429)]);

    expect(fn () => Kernel::browsers()->playwright('sess_1')->execute('return 1;'))->toThrow(RateLimitException::class);

    Http::assertSentCount(1);
});

it('returns raw execution results', function () {
    Http::fake(['*' => Http::response(['success' => true, 'result' => 'Example Domain', 'stdout' => "log\n"])]);

    $result = Kernel::browsers()->playwright('sess_1')->execute('await page.goto("https://example.com"); return await page.title();');

    expect($result->success)->toBeTrue()
        ->and($result->result)->toBe('Example Domain')
        ->and($result->stdout)->toBe("log\n");

    Http::assertSent(fn (Request $request) => $request->data() === ['code' => 'await page.goto("https://example.com"); return await page.title();']);
});

it('runs scripts from a browser object', function () {
    Http::fake([
        'api.onkernel.com/browsers/sess_1/playwright/execute' => Http::response(['success' => true, 'result' => ['results' => [], 'failure' => null]]),
        'api.onkernel.com/browsers/sess_1' => Http::response([
            'session_id' => 'sess_1', 'cdp_ws_url' => 'wss://c', 'webdriver_ws_url' => 'wss://w', 'created_at' => '2026-11-25T10:00:00Z',
            'region' => 'us-east', 'headless' => true, 'stealth' => false, 'captcha_solver' => ['enabled' => false], 'memory' => '1GiB', 'timeout_seconds' => 60,
        ]),
    ]);

    $browser = Kernel::withApiKey('tenant-key')->browsers()->get('sess_1');

    expect($browser)->toBeInstanceOf(Browser::class)
        ->and($browser->run(Script::make()->goto('https://example.com'))->passed())->toBeTrue();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/playwright/execute')
        && $request->hasHeader('Authorization', 'Bearer tenant-key'));
});

it('manages executors', function () {
    Http::fake([
        '*/playwright/executors/checkout*' => Http::response(null, 204),
        '*/playwright/executors' => Http::response(['executors' => [
            ['name' => 'default', 'busy' => false, 'created_at' => '2026-11-25T10:00:00Z', 'last_used_at' => '2026-11-25T10:01:00Z'],
            ['name' => 'checkout', 'busy' => true, 'target_id' => 'T2', 'url' => 'https://shop.test/cart', 'created_at' => '2026-11-25T10:00:00Z', 'last_used_at' => '2026-11-25T10:02:00Z'],
        ]]),
    ]);

    $playwright = Kernel::browsers()->playwright('sess_1');
    $executors = $playwright->executors();

    expect($executors)->toHaveCount(2)
        ->and($executors[1])->toBeInstanceOf(PlaywrightExecutor::class)
        ->and($executors[1]->busy)->toBeTrue()
        ->and($executors[1]->url)->toBe('https://shop.test/cart');

    $playwright->deleteExecutor('checkout', closeTab: false);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.onkernel.com/browsers/sess_1/playwright/executors/checkout?close_tab=false');
});
