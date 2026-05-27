<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class MessageHandlerRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function register(string $prefix, callable $handler): void
    {
        $this->handlers[$prefix] = $handler;
    }

    public function dispatch(Message $message, Discord $discord): void
    {
        $content = trim($message->content ?? '');

        foreach ($this->handlers as $prefix => $handler) {
            if ($content === $prefix) {
                $handler($message, $discord);

                return;
            }

            if (str_starts_with($content, $prefix.' ')) {
                $handler($message, $discord);

                return;
            }
        }
    }
}
