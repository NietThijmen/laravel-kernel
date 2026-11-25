<?php

namespace Nietthijmen\LaravelKernel\Support;

use Nietthijmen\LaravelKernel\LaravelKernel;
use WeakMap;

/**
 * Remembers which client fetched a response object (e.g. a Browser), so
 * methods on that object use the same API key — including clients made
 * with `withApiKey()`.
 *
 * A WeakMap is used instead of a property so the client (and its API key)
 * never ends up in `dd()` output or in serialized objects such as queued
 * jobs. Objects without a binding fall back to the container's client.
 */
final class ClientBinding
{
    /** @var WeakMap<object, LaravelKernel>|null */
    private static ?WeakMap $clients = null;

    public static function bind(object $object, LaravelKernel $client): void
    {
        self::$clients ??= new WeakMap;
        self::$clients[$object] = $client;
    }

    public static function for(object $object): LaravelKernel
    {
        return self::$clients[$object] ?? app(LaravelKernel::class);
    }
}
