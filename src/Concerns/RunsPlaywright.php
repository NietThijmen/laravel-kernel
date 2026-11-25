<?php

namespace Nietthijmen\LaravelKernel\Concerns;

use Nietthijmen\LaravelKernel\Data\PlaywrightResult;
use Nietthijmen\LaravelKernel\Playwright\Script;
use Nietthijmen\LaravelKernel\Playwright\ScriptResult;
use Nietthijmen\LaravelKernel\Resources\Playwright;
use Nietthijmen\LaravelKernel\Support\ClientBinding;

trait RunsPlaywright
{
    public function playwright(): Playwright
    {
        return new Playwright(ClientBinding::for($this), $this->sessionId);
    }

    /**
     * Run a Playwright Script against this browser.
     *
     *   $browser->run(Script::make()->goto('https://example.com')->assertSee('Example'))->throw();
     */
    public function run(Script $script, ?string $executor = null, ?int $timeout = null): ScriptResult
    {
        return $this->playwright()->run($script, $executor, $timeout);
    }

    /**
     * Execute raw Playwright code against this browser.
     */
    public function execute(string $code, ?string $executor = null, ?int $timeout = null): PlaywrightResult
    {
        return $this->playwright()->execute($code, $executor, $timeout);
    }
}
