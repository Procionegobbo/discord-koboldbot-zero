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
            `!kricetta` — Ottieni una ricetta casuale dal mondo dei coboldi
            `!kmestiere` — Scopri un mestiere casuale dal mondo dei coboldi
            TEXT;

        $message->channel->sendMessage($help);
    }
}
