<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Nietthijmen\LaravelKernel\Data\Browser;
use Nietthijmen\LaravelKernel\Exceptions\KernelException;
use Nietthijmen\LaravelKernel\Facades\LaravelKernel as Kernel;
use Nietthijmen\LaravelKernel\Resources\Computer;

beforeEach(fn () => Http::preventStrayRequests());

function sessionPayload_(): array
{
    return [
        'session_id' => 'sess_1',
        'cdp_ws_url' => 'wss://cdp',
        'webdriver_ws_url' => 'wss://wd',
        'created_at' => '2026-11-25T10:00:00Z',
        'region' => 'us-east',
        'headless' => false,
        'stealth' => false,
        'captcha_solver' => ['enabled' => false],
        'memory' => '8GiB',
        'timeout_seconds' => 600,
    ];
}

/**
 * @return list<array{0: string, 1: array<string, mixed>}>
 */
function computerCalls_(): array
{
    return Http::recorded()
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $request) => str_contains($request->url(), '/computer/'))
        ->map(fn (Request $request) => [
            str($request->url())->after('/computer/')->toString(),
            $request->data(),
        ])
        ->values()
        ->all();
}

it('chains click and fill on a browser', function () {
    Http::fake([
        'api.onkernel.com/browsers/sess_1/computer/*' => Http::response(),
        'api.onkernel.com/browsers/sess_1' => Http::response(sessionPayload_()),
    ]);

    $browser = Kernel::browsers()->get('sess_1');

    $result = $browser
        ->click(100, 200)
        ->fill(300, 400, 'me@example.com')
        ->press('Return');

    expect($result)->toBe($browser)
        ->and(computerCalls_())->toBe([
            ['click_mouse', ['x' => 100, 'y' => 200, 'button' => 'left', 'num_clicks' => 1]],
            ['batch', ['actions' => [
                ['type' => 'click_mouse', 'click_mouse' => ['x' => 300, 'y' => 400, 'button' => 'left', 'num_clicks' => 1]],
                ['type' => 'press_key', 'press_key' => ['keys' => ['Ctrl+a']]],
                ['type' => 'type_text', 'type_text' => ['text' => 'me@example.com']],
            ]]],
            ['press_key', ['keys' => ['Return']]],
        ]);
});

it('clears a field when filling with an empty string', function () {
    Http::fake(['*' => Http::response()]);

    Kernel::browsers()->computer('sess_1')->fill(10, 20, '');

    expect(computerCalls_()[0][1]['actions'][2])->toBe(['type' => 'press_key', 'press_key' => ['keys' => ['BackSpace']]]);
});

it('sends the other computer actions', function () {
    Http::fake(['*' => Http::response()]);
    Sleep::fake();

    Kernel::browsers()->computer('sess_1')
        ->doubleClick(1, 2)
        ->rightClick(3, 4)
        ->click(5, 6, holdKeys: ['Ctrl'])
        ->type('hello', delay: 50)
        ->press(['Ctrl+t', 'Tab'], duration: 100)
        ->moveMouse(7, 8, smooth: true)
        ->drag([[0, 0], [10, 10]])
        ->scroll(9, 10, deltaY: 3)
        ->pause(250)
        ->hideCursor()
        ->writeClipboard('copied');

    expect(computerCalls_())->toBe([
        ['click_mouse', ['x' => 1, 'y' => 2, 'button' => 'left', 'num_clicks' => 2]],
        ['click_mouse', ['x' => 3, 'y' => 4, 'button' => 'right', 'num_clicks' => 1]],
        ['click_mouse', ['x' => 5, 'y' => 6, 'button' => 'left', 'num_clicks' => 1, 'hold_keys' => ['Ctrl']]],
        ['type', ['text' => 'hello', 'delay' => 50]],
        ['press_key', ['keys' => ['Ctrl+t', 'Tab'], 'duration' => 100]],
        ['move_mouse', ['x' => 7, 'y' => 8, 'smooth' => true]],
        ['drag_mouse', ['path' => [[0, 0], [10, 10]], 'button' => 'left']],
        ['scroll', ['x' => 9, 'y' => 10, 'delta_x' => 0, 'delta_y' => 3]],
        ['cursor', ['hidden' => true]],
        ['clipboard/write', ['text' => 'copied']],
    ]);

    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds === 250.0);
});

it('returns screenshots, mouse position and clipboard contents', function () {
    Http::fake([
        '*/computer/screenshot' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        '*/computer/get_mouse_position' => Http::response(['x' => 12, 'y' => 34]),
        '*/computer/clipboard/read' => Http::response(['text' => 'hi']),
    ]);

    $computer = Kernel::browsers()->computer('sess_1');

    expect($computer->screenshot())->toBe('PNGDATA')
        ->and($computer->screenshot(0, 0, 100, 50))->toBe('PNGDATA')
        ->and($computer->mousePosition())->toBe(['x' => 12, 'y' => 34])
        ->and($computer->readClipboard())->toBe('hi')
        ->and(computerCalls_()[1])->toBe(['screenshot', ['region' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 50]]]);
});

it('uses the client that fetched the browser', function () {
    Http::fake([
        'api.onkernel.com/browsers/sess_1/computer/*' => Http::response(),
        'api.onkernel.com/browsers' => Http::response(sessionPayload_()),
    ]);

    Kernel::withApiKey('tenant-key')->browsers()->create()->click(1, 1);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/computer/click_mouse')
        && $request->hasHeader('Authorization', 'Bearer tenant-key'));
});

it('falls back to the container client for unbound browsers', function () {
    Http::fake(['*' => Http::response()]);

    $browser = unserialize(serialize(Browser::fromArray(sessionPayload_())));

    $browser->click(1, 1);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('does not leak the client into dumps or serialized browsers', function () {
    Http::fake(['*' => Http::response(sessionPayload_())]);

    $browser = Kernel::withApiKey('secret-key')->browsers()->get('sess_1');

    expect(serialize($browser))->not->toContain('secret-key')
        ->and(print_r($browser, true))->not->toContain('secret-key');
});

it('throws when a computer action fails', function () {
    Http::fake(['*' => Http::response(['code' => 'not_found', 'message' => 'Browser not found'], 404)]);

    expect(fn () => Kernel::browsers()->computer('gone')->click(1, 1))->toThrow(KernelException::class, 'Browser not found');
});

it('url-encodes the browser in computer endpoints', function () {
    Http::fake(['*' => Http::response()]);

    expect(Kernel::browsers()->computer('my browser'))->toBeInstanceOf(Computer::class);
    Kernel::browsers()->computer('my browser')->type('x');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.onkernel.com/browsers/my%20browser/computer/type');
});
