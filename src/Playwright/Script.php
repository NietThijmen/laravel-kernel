<?php

namespace Nietthijmen\LaravelKernel\Playwright;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Stringable;

/**
 * Fluent builder that generates Playwright code for
 * `POST /browsers/{id}/playwright/execute`.
 *
 *   Script::make()
 *       ->goto('https://example.com/register')
 *       ->fill(Locator::label('Email'), 'me@example.com')
 *       ->click(Locator::role('button', 'Sign up'))
 *       ->assertSee('Welcome')
 *       ->text('h1', as: 'heading');
 *
 * Every step is labelled. If a step throws, the remaining steps are skipped
 * and the result reports which step failed and why (see ScriptResult).
 * Selectors accept a string (CSS / Playwright selector) or a Locator.
 * All values are JSON-encoded, so user input cannot inject code.
 */
class Script implements Stringable
{
    use Conditionable;
    use Macroable;

    /** @var list<array{label: string, code: string}> */
    protected array $steps = [];

    protected ?int $timeout = null;

    protected bool $screenshotOnFailure = false;

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    /**
     * Timeout in milliseconds for each action, wait and assertion.
     * Defaults to Playwright's 30s for actions and 5s for assertions.
     */
    public function timeout(int $milliseconds): static
    {
        $this->timeout = $milliseconds;

        return $this;
    }

    /**
     * Capture a screenshot of the page when a step fails.
     */
    public function screenshotOnFailure(bool $enabled = true): static
    {
        $this->screenshotOnFailure = $enabled;

        return $this;
    }

    /**
     * Add a step with custom JavaScript. The code can use `page`, `context`,
     * `browser` and `results` (an object whose properties are returned).
     * Don't `return` from it; store values on `results` instead.
     */
    public function step(string $label, string $code): static
    {
        $this->steps[] = ['label' => $label, 'code' => $code];

        return $this;
    }

    /**
     * Alias of step() with a default label.
     */
    public function raw(string $code, string $label = 'custom code'): static
    {
        return $this->step($label, $code);
    }

    // ------------------------------------------------------------------
    // Navigation & waiting
    // ------------------------------------------------------------------

    /**
     * @param  'load'|'domcontentloaded'|'networkidle'|'commit'  $waitUntil
     */
    public function goto(string $url, string $waitUntil = 'load'): static
    {
        return $this->step("go to {$url}", 'await page.goto('.Js::value($url).', { waitUntil: '.Js::value($waitUntil).', timeout: __timeout });');
    }

    public function reload(): static
    {
        return $this->step('reload', 'await page.reload({ timeout: __timeout });');
    }

    public function back(): static
    {
        return $this->step('go back', 'await page.goBack({ timeout: __timeout });');
    }

    /**
     * Wait until the URL matches an exact URL or a glob pattern with `**` wildcards.
     */
    public function waitForUrl(string $pattern): static
    {
        return $this->step("wait for url {$pattern}", 'await page.waitForURL('.Js::value($pattern).', { timeout: __timeout });');
    }

    /**
     * @param  'load'|'domcontentloaded'|'networkidle'  $state
     */
    public function waitForLoadState(string $state = 'load'): static
    {
        return $this->step("wait for {$state}", 'await page.waitForLoadState('.Js::value($state).', { timeout: __timeout });');
    }

    /**
     * @param  'visible'|'hidden'|'attached'|'detached'  $state
     */
    public function waitFor(string|Locator $selector, string $state = 'visible'): static
    {
        $locator = Locator::from($selector);

        return $this->step("wait for {$locator->describe()} to be {$state}", "await {$locator->toJs()}.first().waitFor({ state: ".Js::value($state).', timeout: __timeout });');
    }

    public function waitForText(string $text): static
    {
        return $this->waitFor(Locator::text($text));
    }

    public function pause(int $milliseconds): static
    {
        return $this->step("pause {$milliseconds}ms", "await page.waitForTimeout({$milliseconds});");
    }

    // ------------------------------------------------------------------
    // Interaction
    // ------------------------------------------------------------------

    public function click(string|Locator $selector): static
    {
        return $this->locatorAction('click', $selector, 'click({ timeout: __timeout })');
    }

    public function doubleClick(string|Locator $selector): static
    {
        return $this->locatorAction('double click', $selector, 'dblclick({ timeout: __timeout })');
    }

    public function hover(string|Locator $selector): static
    {
        return $this->locatorAction('hover', $selector, 'hover({ timeout: __timeout })');
    }

    public function focus(string|Locator $selector): static
    {
        return $this->locatorAction('focus', $selector, 'focus({ timeout: __timeout })');
    }

    /**
     * Clear the field and set its value.
     */
    public function fill(string|Locator $selector, string $value): static
    {
        return $this->locatorAction('fill', $selector, 'fill('.Js::value($value).', { timeout: __timeout })');
    }

    public function clear(string|Locator $selector): static
    {
        return $this->locatorAction('clear', $selector, 'clear({ timeout: __timeout })');
    }

    /**
     * Type key by key (fires keydown/keypress/keyup for every character),
     * for inputs with custom key handling. Prefer fill() otherwise.
     */
    public function type(string|Locator $selector, string $text, int $delay = 0): static
    {
        return $this->locatorAction('type into', $selector, 'pressSequentially('.Js::value($text).", { delay: {$delay}, timeout: __timeout })");
    }

    /**
     * Press a key on an element, e.g. press('#search', 'Enter') or 'Control+a'.
     */
    public function press(string|Locator $selector, string $key): static
    {
        return $this->locatorAction("press {$key} on", $selector, 'press('.Js::value($key).', { timeout: __timeout })');
    }

    /**
     * Press a key on whatever currently has focus.
     */
    public function keyboard(string $key): static
    {
        return $this->step("press {$key}", 'await page.keyboard.press('.Js::value($key).');');
    }

    public function check(string|Locator $selector): static
    {
        return $this->locatorAction('check', $selector, 'check({ timeout: __timeout })');
    }

    public function uncheck(string|Locator $selector): static
    {
        return $this->locatorAction('uncheck', $selector, 'uncheck({ timeout: __timeout })');
    }

    /**
     * Select option(s) of a <select> by value or label.
     *
     * @param  string|list<string>  $values
     */
    public function select(string|Locator $selector, string|array $values): static
    {
        return $this->locatorAction('select', $selector, 'selectOption('.Js::value($values).', { timeout: __timeout })');
    }

    /**
     * Attach a file to a file input. The contents are sent along with the
     * script, so the file does not need to exist on the browser VM.
     */
    public function attach(string|Locator $selector, string $filename, string $contents, string $mimeType = 'application/octet-stream'): static
    {
        $file = '{ name: '.Js::value($filename).', mimeType: '.Js::value($mimeType).', buffer: Buffer.from('.Js::value(base64_encode($contents)).", 'base64') }";

        return $this->locatorAction("attach {$filename} to", $selector, "setInputFiles([{$file}], { timeout: __timeout })");
    }

    // ------------------------------------------------------------------
    // Assertions (retried until they pass or the timeout is reached)
    // ------------------------------------------------------------------

    public function assertSee(string $text): static
    {
        return $this->assertion("see \"{$text}\"", '[(await page.locator("body").innerText()).includes('.Js::value($text).')]', "Expected to see \"{$text}\" on the page");
    }

    public function assertDontSee(string $text): static
    {
        return $this->assertion("don't see \"{$text}\"", '[!(await page.locator("body").innerText()).includes('.Js::value($text).')]', "Expected not to see \"{$text}\" on the page");
    }

    public function assertSeeIn(string|Locator $selector, string $text): static
    {
        $locator = Locator::from($selector);

        return $this->assertion(
            "see \"{$text}\" in {$locator->describe()}",
            "(async () => { const actual = (await {$locator->toJs()}.allInnerTexts()).join('\\n'); return [actual.includes(".Js::value($text).'), actual]; })()',
            "Expected {$locator->describe()} to contain \"{$text}\"",
        );
    }

    public function assertVisible(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is visible", "[await {$locator->toJs()}.first().isVisible()]", "Expected {$locator->describe()} to be visible");
    }

    /**
     * Element is hidden or not on the page.
     */
    public function assertMissing(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is missing", "[!(await {$locator->toJs()}.first().isVisible())]", "Expected {$locator->describe()} to be hidden or missing");
    }

    /**
     * Element is in the DOM (visible or not).
     */
    public function assertPresent(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is present", "[(await {$locator->toJs()}.count()) > 0]", "Expected {$locator->describe()} to be present");
    }

    public function assertCount(string|Locator $selector, int $count): static
    {
        $locator = Locator::from($selector);

        return $this->assertion(
            "{$locator->describe()} count is {$count}",
            "(async () => { const actual = await {$locator->toJs()}.count(); return [actual === {$count}, actual]; })()",
            "Expected {$count} element(s) matching {$locator->describe()}",
        );
    }

    public function assertInputValue(string|Locator $selector, string $value): static
    {
        $locator = Locator::from($selector);

        return $this->assertion(
            "{$locator->describe()} has value \"{$value}\"",
            "(async () => { const actual = await {$locator->toJs()}.first().inputValue({ timeout: 1000 }); return [actual === ".Js::value($value).', actual]; })()',
            "Expected {$locator->describe()} to have value \"{$value}\"",
        );
    }

    /**
     * The selected value of a <select>.
     */
    public function assertSelected(string|Locator $selector, string $value): static
    {
        return $this->assertInputValue($selector, $value);
    }

    public function assertChecked(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is checked", "[await {$locator->toJs()}.first().isChecked({ timeout: 1000 })]", "Expected {$locator->describe()} to be checked");
    }

    public function assertNotChecked(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is not checked", "[!(await {$locator->toJs()}.first().isChecked({ timeout: 1000 }))]", "Expected {$locator->describe()} not to be checked");
    }

    public function assertEnabled(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is enabled", "[await {$locator->toJs()}.first().isEnabled({ timeout: 1000 })]", "Expected {$locator->describe()} to be enabled");
    }

    public function assertDisabled(string|Locator $selector): static
    {
        $locator = Locator::from($selector);

        return $this->assertion("{$locator->describe()} is disabled", "[await {$locator->toJs()}.first().isDisabled({ timeout: 1000 })]", "Expected {$locator->describe()} to be disabled");
    }

    public function assertTitle(string $title): static
    {
        return $this->assertion(
            "title is \"{$title}\"",
            '(async () => { const actual = await page.title(); return [actual === '.Js::value($title).', actual]; })()',
            "Expected the page title to be \"{$title}\"",
        );
    }

    public function assertTitleContains(string $text): static
    {
        return $this->assertion(
            "title contains \"{$text}\"",
            '(async () => { const actual = await page.title(); return [actual.includes('.Js::value($text).'), actual]; })()',
            "Expected the page title to contain \"{$text}\"",
        );
    }

    /**
     * The full URL equals `$url`.
     */
    public function assertUrlIs(string $url): static
    {
        return $this->assertion("url is {$url}", '[page.url() === '.Js::value($url).', page.url()]', "Expected the URL to be {$url}");
    }

    /**
     * The URL path equals `$path`, e.g. "/dashboard".
     */
    public function assertPathIs(string $path): static
    {
        return $this->assertion("path is {$path}", '[new URL(page.url()).pathname === '.Js::value($path).', new URL(page.url()).pathname]', "Expected the path to be {$path}");
    }

    // ------------------------------------------------------------------
    // Extracting data (available on the result under the given key)
    // ------------------------------------------------------------------

    public function title(string $as = 'title'): static
    {
        return $this->extract($as, 'page title', 'await page.title()');
    }

    public function url(string $as = 'url'): static
    {
        return $this->extract($as, 'url', 'page.url()');
    }

    public function text(string|Locator $selector, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "text of {$locator->describe()}", "await {$locator->toJs()}.first().innerText({ timeout: __timeout })");
    }

    /**
     * The text of every matching element, as a list.
     */
    public function texts(string|Locator $selector, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "texts of {$locator->describe()}", "await {$locator->toJs()}.allInnerTexts()");
    }

    public function value(string|Locator $selector, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "value of {$locator->describe()}", "await {$locator->toJs()}.first().inputValue({ timeout: __timeout })");
    }

    public function attribute(string|Locator $selector, string $attribute, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "{$attribute} of {$locator->describe()}", "await {$locator->toJs()}.first().getAttribute(".Js::value($attribute).', { timeout: __timeout })');
    }

    public function count(string|Locator $selector, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "count of {$locator->describe()}", "await {$locator->toJs()}.count()");
    }

    public function isVisible(string|Locator $selector, string $as): static
    {
        $locator = Locator::from($selector);

        return $this->extract($as, "visibility of {$locator->describe()}", "await {$locator->toJs()}.first().isVisible()");
    }

    /**
     * The HTML of an element, or of the whole page when no selector is given.
     */
    public function html(string|Locator|null $selector = null, string $as = 'html'): static
    {
        if ($selector === null) {
            return $this->extract($as, 'page html', 'await page.content()');
        }

        $locator = Locator::from($selector);

        return $this->extract($as, "html of {$locator->describe()}", "await {$locator->toJs()}.first().innerHTML({ timeout: __timeout })");
    }

    /**
     * Evaluate a JavaScript expression or function in the page, e.g.
     * evaluate('document.querySelectorAll("form").length', as: 'forms').
     */
    public function evaluate(string $expression, string $as): static
    {
        // A function passed as a string is not invoked by Playwright, so
        // wrap the expression and call it when it evaluates to a function.
        $wrapped = '(async () => { const value = ('.$expression.'); return typeof value === "function" ? await value() : value; })()';

        return $this->extract($as, 'evaluate', 'await page.evaluate('.Js::value($wrapped).')');
    }

    /**
     * Take a screenshot (base64 PNG). Read it with $result->screenshot($as).
     */
    public function screenshot(string $as = 'screenshot', bool $fullPage = false, string|Locator|null $selector = null): static
    {
        $target = $selector === null
            ? 'page.screenshot({ fullPage: '.Js::value($fullPage).', timeout: __timeout })'
            : Locator::from($selector)->toJs().'.first().screenshot({ timeout: __timeout })';

        return $this->extract($as, 'screenshot', "(await {$target}).toString('base64')");
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return array_column($this->steps, 'label');
    }

    /**
     * The generated JavaScript. It returns
     * { results: {...}, failure: null|{ step, label, message, screenshot? }, steps: n }.
     */
    public function toJs(): string
    {
        $lines = [
            'const __timeout = '.($this->timeout === null ? 'undefined' : $this->timeout).';',
            'const __labels = '.Js::value($this->labels()).';',
            'const results = {};',
            'let __current = -1;',
            'let __failure = null;',
            'const __assert = async (check, message) => {',
            '  const deadline = Date.now() + (__timeout ?? 5000);',
            '  for (;;) {',
            '    let pass = false, actual, error;',
            '    try { [pass, actual] = await check(); } catch (e) { error = e; }',
            '    if (pass) return;',
            '    if (Date.now() >= deadline) {',
            '      throw new Error(message + (error ? `: ${error.message}` : actual !== undefined ? ` (actual: ${JSON.stringify(actual)})` : \'\'));',
            '    }',
            '    await page.waitForTimeout(100);',
            '  }',
            '};',
            'try {',
        ];

        foreach ($this->steps as $index => $step) {
            $lines[] = "  __current = {$index};";
            $lines[] = '  {';
            foreach (explode("\n", $step['code']) as $line) {
                $lines[] = '    '.$line;
            }
            $lines[] = '  }';
        }

        $lines[] = '} catch (e) {';
        $lines[] = '  __failure = { step: __current, label: __labels[__current] ?? null, message: String(e && e.message ? e.message : e).replace(/\\u001b\\[[0-9;]*m/g, "").trim() };';

        if ($this->screenshotOnFailure) {
            $lines[] = "  try { __failure.screenshot = (await page.screenshot()).toString('base64'); } catch {}";
        }

        $lines[] = '}';
        $lines[] = 'return { results, failure: __failure, steps: __labels.length };';

        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return $this->toJs();
    }

    protected function locatorAction(string $verb, string|Locator $selector, string $call): static
    {
        $locator = Locator::from($selector);

        return $this->step("{$verb} {$locator->describe()}", "await {$locator->toJs()}.{$call};");
    }

    /**
     * @param  string  $check  JS expression resolving to [pass, actual?].
     */
    protected function assertion(string $label, string $check, string $message): static
    {
        return $this->step("assert {$label}", 'await __assert(async () => '.$check.', '.Js::value($message).');');
    }

    protected function extract(string $key, string $label, string $expression): static
    {
        return $this->step("get {$label}", 'results['.Js::value($key)."] = {$expression};");
    }
}
