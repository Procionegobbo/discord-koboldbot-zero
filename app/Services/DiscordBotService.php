<?php

namespace App\Services;

use App\Handlers\MessageHandlerRegistry;
use Discord\Discord;
use Discord\WebSockets\Event;

class DiscordBotService
{
    public function __construct(
        private readonly Discord $discord,
        private readonly MessageHandlerRegistry $registry,
    ) {}

    public function start(): void
    {
        $this->discord->on(Event::MESSAGE_CREATE, function ($message): void {
            $this->registry->dispatch($message, $this->discord);
        });

        $this->discord->run();
    }
}
