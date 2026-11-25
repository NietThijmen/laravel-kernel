<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Nietthijmen\LaravelKernel\Data\PlaywrightExecutor;
use Nietthijmen\LaravelKernel\Data\PlaywrightResult;
use Nietthijmen\LaravelKernel\LaravelKernel;
use Nietthijmen\LaravelKernel\Playwright\Script;
use Nietthijmen\LaravelKernel\Playwright\ScriptResult;

/**
 * Run Playwright code against one browser session.
 *
 * @see https://www.kernel.sh/docs/api-reference/browser-playwright
 */
class Playwright extends BaseResource
{
    public function __construct(LaravelKernel $kernel, protected string $browser)
    {
        parent::__construct($kernel);
    }

    /**
     * Run a Script and interpret its outcome.
     *
     * @param  string|null  $executor  Named executor (own tab, runs concurrently with others). Defaults to "default".
     * @param  int|null  $timeout  Maximum execution time in seconds (1-300, API default 60).
     */
    public function run(Script $script, ?string $executor = null, ?int $timeout = null): ScriptResult
    {
        return ScriptResult::fromExecution($this->execute($script, $executor, $timeout), $script);
    }

    /**
     * Execute raw Playwright/TypeScript code. It has access to `page`,
     * `context` and `browser`, and can `return` a value.
     *
     *   execute('await page.goto("https://example.com"); return await page.title();')
     *
     * @param  string|null  $executor  Named executor (own tab, runs concurrently with others). Defaults to "default".
     * @param  int|null  $timeout  Maximum execution time in seconds (1-300, API default 60).
     */
    public function execute(string|Script $code, ?string $executor = null, ?int $timeout = null): PlaywrightResult
    {
        $response = $this->kernel->http()
            // Keep the HTTP request open longer than the execution itself, and
            // never retry: re-running a script could submit a form twice.
            ->timeout(($timeout ?? 60) + 15)
            ->retry(1)
            ->post($this->uri('execute'), $this->filter([
                'code' => $code instanceof Script ? $code->toJs() : $code,
                'executor' => $executor,
                'timeout_sec' => $timeout,
            ]));

        return PlaywrightResult::fromArray($this->kernel->ensureSuccessful($response)->json());
    }

    /**
     * @return list<PlaywrightExecutor>
     */
    public function executors(): array
    {
        $response = $this->getJson($this->uri('executors'));

        return array_map(PlaywrightExecutor::fromArray(...), $response['executors'] ?? []);
    }

    /**
     * Stop a named executor (and close its tab), or restart the default one.
     */
    public function deleteExecutor(string $name, bool $closeTab = true): void
    {
        $this->kernel->request('DELETE', $this->uri('executors/'.rawurlencode($name)), $closeTab ? [] : ['close_tab' => 'false']);
    }

    protected function uri(string $endpoint): string
    {
        return $this->path('/browsers/%s/playwright/', $this->browser).$endpoint;
    }
}
