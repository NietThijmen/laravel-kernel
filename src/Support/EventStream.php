<?php

namespace Nietthijmen\LaravelKernel\Support;

use Generator;
use IteratorAggregate;
use Nietthijmen\LaravelKernel\Data\Events\Event;
use Psr\Http\Message\StreamInterface;

/**
 * Reads a Server-Sent Events stream from the Kernel API and yields each
 * event as a typed object (LogEvent, DeploymentStateEvent, ErrorEvent, ...).
 *
 * @implements IteratorAggregate<int, Event>
 */
class EventStream implements IteratorAggregate
{
    public function __construct(
        protected StreamInterface $stream,
        protected bool $includeHeartbeats = false,
    ) {}

    /**
     * @return Generator<int, Event>
     */
    public function getIterator(): Generator
    {
        $buffer = '';
        $data = [];

        while (! $this->stream->eof()) {
            $chunk = $this->stream->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (preg_match('/\r\n|\n|\r/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
                $line = substr($buffer, 0, $match[0][1]);
                $buffer = substr($buffer, $match[0][1] + strlen($match[0][0]));

                if ($line === '') {
                    if (($event = $this->dispatch($data)) !== null) {
                        yield $event;
                    }
                    $data = [];

                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5), ' ');
                }
                // `event:`, `id:`, `retry:` and `:` comment lines are ignored;
                // the JSON payload already carries its own `event` field.
            }
        }

        if ($buffer !== '' && str_starts_with($buffer, 'data:')) {
            $data[] = ltrim(substr($buffer, 5), ' ');
        }

        if (($event = $this->dispatch($data)) !== null) {
            yield $event;
        }
    }

    /**
     * @param  array<int, string>  $data
     */
    protected function dispatch(array $data): ?Event
    {
        if ($data === []) {
            return null;
        }

        $payload = json_decode(implode("\n", $data), true);

        if (! is_array($payload)) {
            return null;
        }

        if (! $this->includeHeartbeats && ($payload['event'] ?? null) === 'sse_heartbeat') {
            return null;
        }

        return Event::make($payload);
    }
}
