<?php

namespace Nietthijmen\LaravelKernel\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Thrown when the Kernel API returns an error response.
 *
 * The API's error body looks like:
 * {"code": "not_found", "message": "...", "details": [...], "inner_error": {...}}
 */
class KernelException extends RuntimeException
{
    /**
     * @param  array<int, array{code?: string, message?: string}>  $details
     * @param  array{code?: string, message?: string}|null  $innerError
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
        public readonly ?array $innerError = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $status = $response->status();
        $message = $body['message'] ?? (trim($response->body()) ?: "Kernel API request failed with status {$status}.");

        $class = match (true) {
            $status === 401, $status === 403 => AuthenticationException::class,
            $status === 404 => NotFoundException::class,
            $status === 429 => RateLimitException::class,
            in_array($status, [400, 409, 422], true) => ValidationException::class,
            default => self::class,
        };

        return new $class(
            message: (string) $message,
            status: $status,
            errorCode: $body['code'] ?? null,
            details: $body['details'] ?? [],
            innerError: $body['inner_error'] ?? null,
            response: $response,
        );
    }
}
