<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class KciaoHandler
{
    public function __invoke(Message $message, Discord $discord): void
    {
        $message->channel->sendMessage('Ciao! Mi chiamo Kichand, un coboldo stregone specializzato nell\'evocazione di altri coboldi.');
    }
}
