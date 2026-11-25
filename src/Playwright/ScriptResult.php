<?php

namespace Nietthijmen\LaravelKernel\Playwright;

use Illuminate\Support\Arr;
use Nietthijmen\LaravelKernel\Data\PlaywrightResult;
use Nietthijmen\LaravelKernel\Exceptions\ScriptFailedException;

/**
 * The outcome of running a Script.
 *
 * A script fails either when one of its steps throws (an action timed out,
 * an assertion did not pass, ...) or when the execution itself failed
 * (syntax error, execution timeout). Values extracted by steps that ran
 * before the failure are still available.
 */
final class ScriptResult
{
    /**
     * @param  array<string, mixed>  $results
     * @param  int|null  $failedStep  Zero-based index of the step that failed.
     * @param  list<string>  $labels
     */
    public function __construct(
        public readonly PlaywrightResult $execution,
        public readonly array $results,
        public readonly ?int $failedStep,
        public readonly ?string $failedStepLabel,
        public readonly ?string $error,
        public readonly ?string $failureScreenshotBase64,
        public readonly array $labels,
    ) {}

    public static function fromExecution(PlaywrightResult $execution, Script $script): self
    {
        $output = is_array($execution->result) ? $execution->result : [];
        $failure = is_array($output['failure'] ?? null) ? $output['failure'] : null;

        if (! $execution->success) {
            $error = $execution->error ?? 'Playwright execution failed.';
        } elseif ($failure !== null) {
            $error = (string) ($failure['message'] ?? 'Step failed.');
        } else {
            $error = null;
        }

        $failedStep = isset($failure['step']) && $failure['step'] >= 0 ? (int) $failure['step'] : null;

        return new self(
            execution: $execution,
            results: is_array($output['results'] ?? null) ? $output['results'] : [],
            failedStep: $failedStep,
            failedStepLabel: isset($failure['label']) ? (string) $failure['label'] : null,
            error: $error,
            failureScreenshotBase64: isset($failure['screenshot']) ? (string) $failure['screenshot'] : null,
            labels: $script->labels(),
        );
    }

    public function passed(): bool
    {
        return $this->error === null;
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    /**
     * A value extracted by the script (supports "dot" notation).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->results, $key, $default);
    }

    /**
     * Decoded PNG bytes of a screenshot step.
     */
    public function screenshot(string $key = 'screenshot'): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? (base64_decode($value, true) ?: null) : null;
    }

    /**
     * Decoded PNG bytes of the screenshot taken when a step failed
     * (requires Script::screenshotOnFailure()).
     */
    public function failureScreenshot(): ?string
    {
        return $this->failureScreenshotBase64 === null
            ? null
            : (base64_decode($this->failureScreenshotBase64, true) ?: null);
    }

    /**
     * Labels of the steps that completed successfully.
     *
     * @return list<string>
     */
    public function completedSteps(): array
    {
        if ($this->passed()) {
            return $this->labels;
        }

        return array_slice($this->labels, 0, $this->failedStep ?? 0);
    }

    /**
     * A one-line description of the failure, e.g.
     * 'Step 4 (assert see "Welcome") failed: Expected to see "Welcome" on the page'.
     */
    public function failureMessage(): ?string
    {
        if ($this->passed()) {
            return null;
        }

        return $this->failedStep === null
            ? (string) $this->error
            : sprintf('Step %d (%s) failed: %s', $this->failedStep + 1, $this->failedStepLabel, $this->error);
    }

    /**
     * @throws ScriptFailedException
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new ScriptFailedException($this);
        }

        return $this;
    }
}
