<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kernel API key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate against the Kernel API. Create one in
    | the Kernel dashboard. It is sent as a bearer token on every request.
    |
    */

    'api_key' => env('KERNEL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('KERNEL_BASE_URL', 'https://api.onkernel.com'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts & retries
    |--------------------------------------------------------------------------
    |
    | `timeout` is the request timeout in seconds. Streaming (SSE) endpoints
    | ignore it and stay open until the server closes the stream.
    |
    | `retries` is how many times a request is retried after a connection
    | error or a 429 (Too Many Requests) response. Other errors are never
    | retried, so non-idempotent requests are not sent twice on a 5xx.
    |
    */

    'timeout' => (int) env('KERNEL_TIMEOUT', 60),

    'retries' => (int) env('KERNEL_RETRIES', 2),

    'retry_delay' => (int) env('KERNEL_RETRY_DELAY', 500),

];
