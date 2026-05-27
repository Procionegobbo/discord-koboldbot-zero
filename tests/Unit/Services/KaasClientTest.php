<?php

use App\Services\KaasClient;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;

function fakeResponse(int $status, string $body): ResponseInterface
{
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn($status);
    $response->shouldReceive('getBody')->andReturn($body);

    return $response;
}

it('resolves with the response body on HTTP 200', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->with(
            'https://kaas.procionegobbo.it/api/generate-kobold',
            ['Content-Type' => 'application/json', 'X-Bypass-Key' => config('discord.kaas_bypass_key')],
            '{"language":"it"}'
        )
        ->andReturn(\React\Promise\resolve(fakeResponse(200, '{"KoboldName":"Grix"}')));

    $resolved = null;
    (new KaasClient($browser))->generate()->then(function (string $body) use (&$resolved): void {
        $resolved = $body;
    });

    expect($resolved)->toBe('{"KoboldName":"Grix"}');
});

it('rejects with rate_limit RuntimeException on HTTP 429', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\resolve(fakeResponse(429, '')));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and($caught->getMessage())->toBe('rate_limit');
});

it('rejects with http_error RuntimeException on HTTP 503', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\resolve(fakeResponse(503, '')));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and($caught->getMessage())->toBe('http_error:503');
});

it('propagates network-level rejections unchanged', function () {
    $networkError = new RuntimeException('Connection refused');

    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\reject($networkError));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBe($networkError);
});
