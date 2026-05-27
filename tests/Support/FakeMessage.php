<?php

namespace Tests\Support;

use Discord\Discord;
use Discord\Parts\Channel\Message;

/**
 * A test double for DiscordPHP's Message Part.
 *
 * The real Message relies on magic attribute accessors backed by a live
 * Discord client and factory, which makes plain Mockery property assignment
 * unreliable. This subclass stores attributes in a simple array so tests can
 * read `$message->content` and `$message->channel` deterministically.
 */
class FakeMessage extends Message
{
    /** @var array<string, mixed> */
    private array $stubbedAttributes = [];

    public function __construct(Discord $discord, array $attributes = [], bool $created = false)
    {
        $this->stubbedAttributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->stubbedAttributes[$key] ?? null;
    }

    public function __set(string $key, $value): void
    {
        $this->stubbedAttributes[$key] = $value;
    }
}
