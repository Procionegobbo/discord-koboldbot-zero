<?php

use App\Handlers\KoboldoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

it('sends "Koboldo in arrivo!" to the channel', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Koboldo in arrivo!');

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler)($message, $discord);
});
