<?php

use App\Handlers\KmestiereHandler;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

function kmestierePayload(): string
{
    return json_encode(['KoboldJob' => 'Minatore']);
}

it('sends an acknowledgement immediately, then the job on success', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (string $msg): bool => str_contains($msg, 'Minatore')));

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(kmestierePayload()));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends the rate-limit error message on HTTP 429', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Troppi kobold evocati! Riprova tra un secondo.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('rate_limit')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a generic error message on network failure', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('Connection refused')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a parse error message when the API returns malformed JSON', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il messaggio del kobold era illeggibile. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve('not valid json {{'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a portal-closed error on non-429 HTTP error', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('http_error:503')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a generic error when KoboldJob is absent from the API response', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(json_encode(['KoboldDish' => 'Zuppa di funghi'])));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});
