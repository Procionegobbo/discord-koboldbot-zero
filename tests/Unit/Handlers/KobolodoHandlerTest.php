<?php

use App\Handlers\KobolodoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

it('sends "Koboldo in arrivo!" to the channel', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Koboldo in arrivo!');

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KobolodoHandler)($message, $discord);
});
