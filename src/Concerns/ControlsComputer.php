<?php

namespace Nietthijmen\LaravelKernel\Concerns;

use Nietthijmen\LaravelKernel\Resources\Computer;
use Nietthijmen\LaravelKernel\Support\ClientBinding;

/**
 * Chainable computer controls on a Browser:
 *
 *   $browser->click(640, 400)->fill(640, 480, 'me@example.com')->press('Return');
 *
 * @see Computer
 */
trait ControlsComputer
{
    public function computer(): Computer
    {
        return new Computer(ClientBinding::for($this), $this->sessionId);
    }

    /**
     * @param  'left'|'right'|'middle'|'back'|'forward'  $button
     * @param  list<string>  $holdKeys
     */
    public function click(int $x, int $y, string $button = 'left', int $clicks = 1, array $holdKeys = []): static
    {
        $this->computer()->click($x, $y, $button, $clicks, $holdKeys);

        return $this;
    }

    public function doubleClick(int $x, int $y): static
    {
        $this->computer()->doubleClick($x, $y);

        return $this;
    }

    public function rightClick(int $x, int $y): static
    {
        $this->computer()->rightClick($x, $y);

        return $this;
    }

    /**
     * Click into a field, select its contents and replace them with `$text`.
     */
    public function fill(int $x, int $y, string $text, ?int $delay = null): static
    {
        $this->computer()->fill($x, $y, $text, $delay);

        return $this;
    }

    public function type(string $text, ?int $delay = null): static
    {
        $this->computer()->type($text, $delay);

        return $this;
    }

    /**
     * @param  string|list<string>  $keys  xdotool key names, e.g. "Return" or "Ctrl+t".
     * @param  list<string>  $holdKeys
     */
    public function press(string|array $keys, array $holdKeys = [], ?int $duration = null): static
    {
        $this->computer()->press($keys, $holdKeys, $duration);

        return $this;
    }

    public function moveMouse(int $x, int $y, ?bool $smooth = null, ?int $durationMs = null): static
    {
        $this->computer()->moveMouse($x, $y, $smooth, $durationMs);

        return $this;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $path
     * @param  'left'|'middle'|'right'  $button
     * @param  array<string, mixed>  $options
     */
    public function drag(array $path, string $button = 'left', array $options = []): static
    {
        $this->computer()->drag($path, $button, $options);

        return $this;
    }

    public function scroll(int $x, int $y, int $deltaY = 0, int $deltaX = 0): static
    {
        $this->computer()->scroll($x, $y, $deltaY, $deltaX);

        return $this;
    }

    public function pause(int $milliseconds): static
    {
        $this->computer()->pause($milliseconds);

        return $this;
    }

    public function writeClipboard(string $text): static
    {
        $this->computer()->writeClipboard($text);

        return $this;
    }

    public function readClipboard(): string
    {
        return $this->computer()->readClipboard();
    }

    /**
     * @return array{x: int, y: int}
     */
    public function mousePosition(): array
    {
        return $this->computer()->mousePosition();
    }

    /**
     * Capture a screenshot (PNG bytes), optionally of a region.
     */
    public function screenshot(?int $x = null, ?int $y = null, ?int $width = null, ?int $height = null): string
    {
        return $this->computer()->screenshot($x, $y, $width, $height);
    }
}
