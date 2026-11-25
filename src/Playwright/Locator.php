<?php

namespace Nietthijmen\LaravelKernel\Playwright;

use Stringable;

/**
 * Builds Playwright locators. Anywhere a Script accepts a selector you can
 * pass a plain string (CSS, or any Playwright selector such as
 * "text=Sign in" or "xpath=//button") or one of these:
 *
 *   Locator::label('Email')
 *   Locator::role('button', 'Sign up')
 *   Locator::css('.todo-item')->nth(2)
 *
 * @see https://playwright.dev/docs/locators
 */
final class Locator implements Stringable
{
    private function __construct(
        private readonly string $js,
        private readonly string $description,
    ) {}

    public static function from(string|self $selector): self
    {
        return $selector instanceof self ? $selector : self::css($selector);
    }

    /**
     * A CSS selector or any other Playwright selector string.
     */
    public static function css(string $selector): self
    {
        return new self('page.locator('.Js::value($selector).')', $selector);
    }

    /**
     * Form control by its <label>, aria-labelledby or aria-label.
     */
    public static function label(string $text, bool $exact = false): self
    {
        return new self('page.getByLabel('.Js::value($text).', { exact: '.Js::value($exact).' })', "label \"{$text}\"");
    }

    /**
     * Element by ARIA role and accessible name, e.g. role('button', 'Save').
     */
    public static function role(string $role, ?string $name = null, bool $exact = false): self
    {
        $options = $name === null ? '' : ', { name: '.Js::value($name).', exact: '.Js::value($exact).' }';

        return new self('page.getByRole('.Js::value($role).$options.')', $name === null ? "role {$role}" : "{$role} \"{$name}\"");
    }

    public static function text(string $text, bool $exact = false): self
    {
        return new self('page.getByText('.Js::value($text).', { exact: '.Js::value($exact).' })', "text \"{$text}\"");
    }

    public static function placeholder(string $text, bool $exact = false): self
    {
        return new self('page.getByPlaceholder('.Js::value($text).', { exact: '.Js::value($exact).' })', "placeholder \"{$text}\"");
    }

    /**
     * Element by its data-testid attribute.
     */
    public static function testId(string $id): self
    {
        return new self('page.getByTestId('.Js::value($id).')', "test id \"{$id}\"");
    }

    public function first(): self
    {
        return new self($this->js.'.first()', $this->description.' (first)');
    }

    public function last(): self
    {
        return new self($this->js.'.last()', $this->description.' (last)');
    }

    /**
     * The element at a zero-based index.
     */
    public function nth(int $index): self
    {
        return new self($this->js.'.nth('.$index.')', $this->description." (#{$index})");
    }

    /**
     * Narrow down to elements containing the given text.
     */
    public function withText(string $text): self
    {
        return new self($this->js.'.filter({ hasText: '.Js::value($text).' })', $this->description." with text \"{$text}\"");
    }

    /**
     * Find this locator inside another one, e.g. Locator::css('button')->within('form#signup').
     */
    public function within(string|self $parent): self
    {
        $inner = str_starts_with($this->js, 'page.') ? substr($this->js, 5) : $this->js;

        return new self(self::from($parent)->js.'.'.$inner, $this->description.' in '.self::from($parent)->description);
    }

    /**
     * The JavaScript expression for this locator.
     */
    public function toJs(): string
    {
        return $this->js;
    }

    /**
     * A human-readable description, used in step labels.
     */
    public function describe(): string
    {
        return $this->description;
    }

    public function __toString(): string
    {
        return $this->js;
    }
}
