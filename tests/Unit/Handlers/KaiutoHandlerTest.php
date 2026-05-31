<?php

use App\Handlers\KaiutoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

it('sends a help message listing all commands', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(function (string $content): bool {
            return str_contains($content, '!kciao')
                && str_contains($content, '!kaiuto')
                && str_contains($content, '!koboldo')
                && str_contains($content, '!kricetta')
                && str_contains($content, '!kmestiere');
        }));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KaiutoHandler)($message, $discord);
});
