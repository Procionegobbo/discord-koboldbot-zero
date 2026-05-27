<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class KobolodoHandler
{
    public function __invoke(Message $message, Discord $discord): void
    {
        $message->channel->sendMessage('Koboldo in arrivo!');
    }
}
