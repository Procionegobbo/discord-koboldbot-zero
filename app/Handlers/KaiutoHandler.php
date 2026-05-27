<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class KaiutoHandler
{
    public function __invoke(Message $message, Discord $discord): void
    {
        $help = <<<'TEXT'
            **Comandi disponibili:**
            `!kciao` — Il bot ti saluta
            `!kaiuto` — Mostra questo messaggio di aiuto
            `!koboldo` — Evoca Koboldo
            TEXT;

        $message->channel->sendMessage($help);
    }
}
