<?php

use App\Handlers\KciaoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

it('sends a greeting in Italian', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::pattern('/Ciao/i'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KciaoHandler)($message, $discord);
});
