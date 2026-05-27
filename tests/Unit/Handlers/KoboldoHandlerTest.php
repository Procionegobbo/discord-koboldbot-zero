<?php

use App\Handlers\KoboldoHandler;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

// Helper: full valid API payload
function koboldPayload(): string
{
    return json_encode([
        'KoboldName' => 'Grix',
        'KoboldSurname' => 'Baffi',
        'KoboldOrigin' => 'Caverne del Nord',
        'KoboldColor' => 'Verde muschio',
        'SpecialTrait' => 'Vede al buio',
        'KoboldKinship' => 'Clan dei Baffi',
        'KoboldDish' => 'Zuppa di funghi',
        'KoboldStory' => 'Nato sotto una roccia fortunata.',
        'KoboldJob' => 'Minatore',
    ]);
}

it('sends an acknowledgement immediately, then the character sheet on success', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (string $msg): bool => str_contains($msg, 'Grix Baffi') &&
            str_contains($msg, 'Verde muschio') &&
            str_contains($msg, 'Minatore')
        ));

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(koboldPayload()));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends the rate-limit error message on HTTP 429', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Troppi kobold evocati! Riprova tra un secondo.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('rate_limit')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a generic error message on network failure', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('Connection refused')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a parse error message when the API returns malformed JSON', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il messaggio del kobold era illeggibile. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve('not valid json {{'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a portal-closed error on non-429 HTTP error', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('http_error:503')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});
