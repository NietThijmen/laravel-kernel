<?php

namespace Nietthijmen\LaravelKernel\Playwright;

/**
 * @internal Encodes PHP values as JavaScript literals.
 */
final class Js
{
    /**
     * JSON is valid JavaScript, and encoding every value means user input
     * (form values, selectors, URLs) can never break out of its literal.
     */
    public static function value(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
