<?php

namespace Nietthijmen\LaravelKernel\Resources;

use Illuminate\Support\Sleep;
use Nietthijmen\LaravelKernel\LaravelKernel;

/**
 * Computer controls (mouse, keyboard, screenshots, clipboard) for one
 * browser session. Actions return `$this`, so they can be chained:
 *
 *   $computer->click(100, 200)->type('hello')->press('Return');
 *
 * Coordinates are in pixels, relative to the top-left of the browser screen.
 *
 * @see https://www.kernel.sh/docs/api-reference/browser-computer-controls
 */
class Computer extends BaseResource
{
    public function __construct(LaravelKernel $kernel, protected string $browser)
    {
        parent::__construct($kernel);
    }

    /**
     * Click at the given coordinates.
     *
     * @param  'left'|'right'|'middle'|'back'|'forward'  $button
     * @param  list<string>  $holdKeys  Modifier keys to hold, e.g. ['Ctrl'].
     */
    public function click(int $x, int $y, string $button = 'left', int $clicks = 1, array $holdKeys = []): static
    {
        return $this->action('click_mouse', $this->clickPayload($x, $y, $button, $clicks, $holdKeys));
    }

    public function doubleClick(int $x, int $y): static
    {
        return $this->click($x, $y, clicks: 2);
    }

    public function rightClick(int $x, int $y): static
    {
        return $this->click($x, $y, button: 'right');
    }

    /**
     * Click into a field, select its contents and replace them with `$text`.
     *
     * Runs as a single batch request: click, Ctrl+A, then type (or
     * BackSpace when `$text` is empty, to clear the field).
     *
     * @param  int|null  $delay  Delay in milliseconds between keystrokes.
     */
    public function fill(int $x, int $y, string $text, ?int $delay = null): static
    {
        return $this->batch([
            ['type' => 'click_mouse', 'click_mouse' => $this->clickPayload($x, $y)],
            ['type' => 'press_key', 'press_key' => ['keys' => ['Ctrl+a']]],
            $text === ''
                ? ['type' => 'press_key', 'press_key' => ['keys' => ['BackSpace']]]
                : ['type' => 'type_text', 'type_text' => $this->filter(['text' => $text, 'delay' => $delay])],
        ]);
    }

    /**
     * Type text at the current focus.
     *
     * @param  int|null  $delay  Delay in milliseconds between keystrokes.
     */
    public function type(string $text, ?int $delay = null): static
    {
        return $this->action('type', $this->filter(['text' => $text, 'delay' => $delay]));
    }

    /**
     * Press one or more keys, using xdotool / X11 key names:
     * "Return", "Tab", "Escape", "BackSpace", "Ctrl+t", "Ctrl+Shift+Tab", ...
     *
     * @param  string|list<string>  $keys
     * @param  list<string>  $holdKeys  Modifier keys to hold during the sequence.
     * @param  int|null  $duration  Milliseconds to hold the keys down (default: tap).
     */
    public function press(string|array $keys, array $holdKeys = [], ?int $duration = null): static
    {
        return $this->action('press_key', $this->filter([
            'keys' => (array) $keys,
            'hold_keys' => $holdKeys ?: null,
            'duration' => $duration,
        ]));
    }

    /**
     * @param  bool|null  $smooth  Move along a human-like path instead of jumping.
     */
    public function moveMouse(int $x, int $y, ?bool $smooth = null, ?int $durationMs = null): static
    {
        return $this->action('move_mouse', $this->filter([
            'x' => $x,
            'y' => $y,
            'smooth' => $smooth,
            'duration_ms' => $durationMs,
        ]));
    }

    /**
     * Drag the mouse along a path of [x, y] points (at least two).
     *
     * @param  list<array{0: int, 1: int}>  $path
     * @param  'left'|'middle'|'right'  $button
     * @param  array<string, mixed>  $options  e.g. smooth, duration_ms, hold_keys, steps_per_segment, step_delay_ms.
     */
    public function drag(array $path, string $button = 'left', array $options = []): static
    {
        return $this->action('drag_mouse', ['path' => $path, 'button' => $button, ...$options]);
    }

    /**
     * Scroll the mouse wheel at a position. Positive `$deltaY` scrolls down,
     * negative scrolls up (in xdotool wheel units).
     */
    public function scroll(int $x, int $y, int $deltaY = 0, int $deltaX = 0): static
    {
        return $this->action('scroll', ['x' => $x, 'y' => $y, 'delta_x' => $deltaX, 'delta_y' => $deltaY]);
    }

    /**
     * Wait before running the next action in the chain.
     */
    public function pause(int $milliseconds): static
    {
        Sleep::for($milliseconds)->milliseconds();

        return $this;
    }

    public function hideCursor(): static
    {
        return $this->action('cursor', ['hidden' => true]);
    }

    public function showCursor(): static
    {
        return $this->action('cursor', ['hidden' => false]);
    }

    public function writeClipboard(string $text): static
    {
        return $this->action('clipboard/write', ['text' => $text]);
    }

    public function readClipboard(): string
    {
        return (string) ($this->postJson($this->uri('clipboard/read'))['text'] ?? '');
    }

    /**
     * @return array{x: int, y: int}
     */
    public function mousePosition(): array
    {
        $position = $this->postJson($this->uri('get_mouse_position'));

        return ['x' => (int) $position['x'], 'y' => (int) $position['y']];
    }

    /**
     * Capture a screenshot and return the PNG bytes. Optionally limit it to
     * a region.
     */
    public function screenshot(?int $x = null, ?int $y = null, ?int $width = null, ?int $height = null): string
    {
        $region = $width !== null && $height !== null
            ? ['x' => $x ?? 0, 'y' => $y ?? 0, 'width' => $width, 'height' => $height]
            : null;

        $response = $this->kernel->http()
            ->accept('image/png')
            ->post($this->uri('screenshot'), $region === null ? [] : ['region' => $region]);

        return $this->kernel->ensureSuccessful($response)->body();
    }

    /**
     * Run raw computer actions sequentially in one request. Execution stops
     * at the first error.
     *
     * @param  list<array<string, mixed>>  $actions  e.g. [['type' => 'sleep', 'sleep' => ['duration_ms' => 500]]]
     */
    public function batch(array $actions): static
    {
        return $this->action('batch', ['actions' => $actions]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function action(string $endpoint, array $data): static
    {
        $this->postJson($this->uri($endpoint), $data);

        return $this;
    }

    protected function uri(string $endpoint): string
    {
        return $this->path('/browsers/%s/computer/', $this->browser).$endpoint;
    }

    /**
     * @param  list<string>  $holdKeys
     * @return array<string, mixed>
     */
    protected function clickPayload(int $x, int $y, string $button = 'left', int $clicks = 1, array $holdKeys = []): array
    {
        return $this->filter([
            'x' => $x,
            'y' => $y,
            'button' => $button,
            'num_clicks' => $clicks,
            'hold_keys' => $holdKeys ?: null,
        ]);
    }
}
