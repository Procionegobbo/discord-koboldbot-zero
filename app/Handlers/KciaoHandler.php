<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class KciaoHandler
{
    public function __invoke(Message $message, Discord $discord): void
    {
        $message->channel->sendMessage('Ciao! Sono Koboldo, il tuo bot preferito! 🤖');
    }
}
