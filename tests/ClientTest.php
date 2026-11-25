<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Nietthijmen\LaravelKernel\Data\App;
use Nietthijmen\LaravelKernel\Data\Events\DeploymentStateEvent;
use Nietthijmen\LaravelKernel\Data\Events\LogEvent;
use Nietthijmen\LaravelKernel\Enums\DeploymentStatus;
use Nietthijmen\LaravelKernel\Enums\InvocationStatus;
use Nietthijmen\LaravelKernel\Exceptions\KernelException;
use Nietthijmen\LaravelKernel\Exceptions\NotFoundException;
use Nietthijmen\LaravelKernel\Exceptions\RateLimitException;
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;
use Nietthijmen\LaravelKernel\LaravelKernel;

beforeEach(fn () => Http::preventStrayRequests());

function app_(string $name, string $version = 'latest'): array
{
    return [
        'id' => "{$name}-{$version}",
        'app_name' => $name,
        'version' => $version,
        'region' => 'aws.us-east-1a',
        'deployment' => 'dep_1',
        'actions' => [['name' => 'run', 'input_schema' => null, 'output_schema' => null]],
        'env_vars' => [],
    ];
}

function deployment_(string $status): array
{
    return ['id' => 'dep_1', 'status' => $status, 'region' => 'aws.us-east-1a', 'created_at' => '2026-01-01T00:00:00Z'];
}

it('resolves the client from the container and the facade', function () {
    expect(app(LaravelKernel::class))->toBe(app('kernel'))
        ->and(Kernel::getFacadeRoot())->toBeInstanceOf(LaravelKernel::class);
});

it('lists apps with filters, auth and pagination headers', function () {
    Http::fake([
        'api.onkernel.com/apps*' => Http::response([app_('my-app')], 200, [
            'X-Has-More' => 'true',
            'X-Limit' => '1',
            'X-Offset' => '0',
            'X-Next-Offset' => '1',
        ]),
    ]);

    $page = Kernel::apps()->list(['app_name' => 'my-app', 'limit' => 1]);

    expect($page)->toHaveCount(1)
        ->and($page->items[0])->toBeInstanceOf(App::class)
        ->and($page->items[0]->appName)->toBe('my-app')
        ->and($page->items[0]->actions[0]->name)->toBe('run')
        ->and($page->hasMore)->toBeTrue()
        ->and($page->limit)->toBe(1)
        ->and($page->nextOffset)->toBe(1);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://api.onkernel.com/apps?app_name=my-app&limit=1'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('lazily walks every page', function () {
    Http::fake([
        'api.onkernel.com/apps?limit=100&offset=0' => Http::response([app_('a'), app_('b')], 200, ['X-Has-More' => 'true', 'X-Next-Offset' => '2']),
        'api.onkernel.com/apps?limit=100&offset=2' => Http::response([app_('c')], 200, ['X-Has-More' => 'false', 'X-Next-Offset' => '0']),
    ]);

    expect(Kernel::apps()->all()->map(fn (App $app) => $app->appName)->all())->toBe(['a', 'b', 'c']);

    Http::assertSentCount(2);
});

it('throws typed exceptions from the error body', function () {
    Http::fake([
        '*' => Http::response(['code' => 'not_found', 'message' => 'Deployment not found'], 404),
    ]);

    try {
        Kernel::deployments()->get('missing');
        $this->fail('Expected exception');
    } catch (NotFoundException $e) {
        expect($e->getMessage())->toBe('Deployment not found')
            ->and($e->errorCode)->toBe('not_found')
            ->and($e->status)->toBe(404);
    }
});

it('retries on 429 and then gives up', function () {
    config()->set('kernel.retries', 1);
    app()->forgetInstance(LaravelKernel::class);
    Kernel::clearResolvedInstances();

    Http::fake(['*' => Http::response(['code' => 'rate_limited', 'message' => 'Slow down'], 429)]);

    expect(fn () => Kernel::browsers()->list())->toThrow(RateLimitException::class, 'Slow down');

    Http::assertSentCount(2);
});

it('does not retry on server errors', function () {
    Http::fake(['*' => Http::response(['code' => 'internal', 'message' => 'Boom'], 500)]);

    expect(fn () => Kernel::invocations()->create('app', 'run'))->toThrow(KernelException::class, 'Boom');

    Http::assertSentCount(1);
});

it('url-encodes path segments', function () {
    Http::fake(['*' => Http::response([], 204)]);

    Kernel::profiles()->delete('my profile/1');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.onkernel.com/profiles/my%20profile%2F1');
});

it('json-encodes invocation payloads', function () {
    Http::fake(['*' => Http::response(['id' => 'inv_1', 'action_name' => 'run', 'status' => 'queued'], 202)]);

    $invocation = Kernel::invocations()->create('my-app', 'run', ['url' => 'https://example.com'], ['async' => true]);

    expect($invocation->id)->toBe('inv_1')
        ->and($invocation->status)->toBe(InvocationStatus::Queued);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onkernel.com/invocations'
        && $request->data() === [
            'app_name' => 'my-app',
            'action_name' => 'run',
            'payload' => '{"url":"https:\/\/example.com"}',
            'async' => true,
        ]);
});

it('returns null when a pool acquire times out', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    expect(Kernel::browserPools()->acquire('pool', ['acquire_timeout_seconds' => 1]))->toBeNull();
});

it('creates deployments as multipart', function () {
    Http::fake(['*' => Http::response(deployment_('queued'), 201)]);

    $deployment = Kernel::deployments()->create([
        'file' => 'zip-bytes',
        'entrypoint_rel_path' => 'src/app.ts',
        'env_vars' => ['FOO' => 'bar'],
        'force' => true,
    ]);

    expect($deployment->status)->toBe(DeploymentStatus::Queued);

    Http::assertSent(function (Request $request) {
        $body = (string) $request->toPsrRequest()->getBody();

        return $request->isMultipart()
            && str_contains($body, 'name="file"; filename="app.zip"')
            && str_contains($body, 'name="entrypoint_rel_path"')
            && str_contains($body, 'name="env_vars[FOO]"')
            && preg_match('/name="force"\r\n(?:[^\r\n]+\r\n)*\r\ntrue\r\n/', $body) === 1;
    });
});

it('parses server-sent events', function () {
    $sse = implode("\n", [
        'event: log',
        'data: {"event":"log","message":"Building","timestamp":"2026-01-01T00:00:00Z"}',
        '',
        ': keep-alive comment',
        'data: {"event":"sse_heartbeat","timestamp":"2026-01-01T00:00:01Z"}',
        '',
        'data: {"event":"deployment_state",',
        'data: "deployment":'.json_encode(deployment_('running')).',"timestamp":"2026-01-01T00:00:02Z"}',
        '',
    ]);

    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

    $events = iterator_to_array(Kernel::deployments()->events('dep_1', since: '5m'), false);

    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(LogEvent::class)
        ->and($events[0]->message)->toBe('Building')
        ->and($events[1])->toBeInstanceOf(DeploymentStateEvent::class)
        ->and($events[1]->deployment->status)->toBe(DeploymentStatus::Running);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onkernel.com/deployments/dep_1/events?since=5m');
});

it('fails clearly without an api key', function () {
    expect(fn () => (new LaravelKernel(null))->apps()->list())->toThrow(RuntimeException::class, 'KERNEL_API_KEY');
});

it('lists apps from the artisan command', function () {
    Http::fake(['*' => Http::response([app_('my-app')], 200, ['X-Has-More' => 'false'])]);

    $this->artisan('kernel:apps')
        ->expectsTable(['App', 'Version', 'Region', 'Deployment', 'Actions'], [
            ['my-app', 'latest', 'aws.us-east-1a', 'dep_1', 'run'],
        ])
        ->assertSuccessful();
});
