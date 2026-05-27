# Feature: Base Discord Bot

## Feature Name & Description

Implement a long-running Discord gateway bot process using the DiscordPHP library, triggered by a Laravel Zero CLI command, that responds to three Italian-language prefix-based message commands (`!kciao`, `!kaiuto`, `!koboldo`) in any Discord channel the bot has access to.

**Current state**: The project is a fresh Laravel Zero 12 scaffold with no Discord integration. Only the default `InspireCommand` exists.

**Scope in**:
- Installing `team-reflex/discord-php` via Composer
- A `bot:run` Laravel Zero command that starts the long-running Discord client
- A `DiscordBotService` that encapsulates client setup and message handler registration
- Three Discord message command handlers: `!kciao`, `!kaiuto`, `!koboldo`
- Environment-based configuration for the Discord bot token
- Feature tests for the `bot:run` command (with mocked Discord client)
- Unit tests for handler logic

**Scope out**:
- Slash commands (Discord application commands via REST)
- Persistent storage / databases
- KoboldAI integration (future story)
- Voice channel support
- Per-guild or per-channel configuration
- Rate limiting beyond DiscordPHP's built-in handling

---

## Architecture / Design Overview

The Discord bot is a **long-running process** — not a request/response cycle. DiscordPHP uses ReactPHP's event loop internally; the process blocks on `$discord->run()` until it is killed.

The architecture follows the existing Laravel Zero convention: a Command in `app/Commands/` acts as the entry point, delegates all Discord logic to a dedicated service class, and exits only when the event loop terminates.

```
./application bot:run
        │
        ▼
App\Commands\BotRunCommand::handle()
        │  resolves via IoC
        ▼
App\Services\DiscordBotService::start()
        │  creates Discord client
        │  registers 'message' event listener
        │  calls $discord->run()  ← blocks here (ReactPHP loop)
        │
        ▼  on each Discord message event
App\Handlers\MessageHandlerRegistry::dispatch($message)
        │  matches prefix
        ├──► App\Handlers\KciaoHandler
        ├──► App\Handlers\KaiutoHandler
        └──► App\Handlers\KobolodoHandler
```

**Key design decisions**:

1. **Handler registry pattern**: Handlers are small, single-responsibility classes registered in a central registry. This makes adding future commands trivial without touching the service class.
2. **IoC binding**: `DiscordBotService` and `Discord` client are bound in `AppServiceProvider` so they can be swapped in tests without subclassing.
3. **No static state**: Handlers receive the `Message` object and the `Discord` client; they do not hold instance state.
4. **Graceful shutdown**: The command should trap `SIGINT`/`SIGTERM` if the underlying OS supports it (the ReactPHP loop handles this natively on Linux/macOS via `$discord->on('close', ...)`).

---

## Configuration

### Environment Variables

| Variable | Required | Description | Example |
|---|---|---|---|
| `DISCORD_BOT_TOKEN` | Yes | Discord bot token from the Developer Portal | `MTEx…abc` |

Add to `.env` and `.env.example`:

```dotenv
DISCORD_BOT_TOKEN=your-bot-token-here
```

### Config File

Create `config/discord.php`:

```php
<?php

return [
    'token' => env('DISCORD_BOT_TOKEN'),
];
```

This file is auto-loaded by Laravel Zero's configuration system. Access it anywhere via `config('discord.token')`.

### No feature flags are introduced.

---

## Data Model

No persistence is required for this feature. The bot is entirely stateless — it reacts to incoming Discord events and responds; no data is written to disk or a database.

---

## Impact on Existing Code

### Files to Create

| Path | Purpose |
|---|---|
| `app/Commands/BotRunCommand.php` | Laravel Zero command; entry point for the bot process |
| `app/Services/DiscordBotService.php` | Encapsulates Discord client construction and event loop |
| `app/Handlers/MessageHandlerRegistry.php` | Dispatches messages to the correct handler |
| `app/Handlers/KciaoHandler.php` | Handles `!kciao` |
| `app/Handlers/KaiutoHandler.php` | Handles `!kaiuto` |
| `app/Handlers/KobolodoHandler.php` | Handles `!koboldo` |
| `config/discord.php` | Discord configuration values |
| `tests/Feature/BotRunCommandTest.php` | Feature test for the CLI command |
| `tests/Unit/MessageHandlerRegistryTest.php` | Unit tests for handler dispatch logic |
| `tests/Unit/Handlers/KciaoHandlerTest.php` | Unit tests for `!kciao` |
| `tests/Unit/Handlers/KaiutoHandlerTest.php` | Unit tests for `!kaiuto` |
| `tests/Unit/Handlers/KobolodoHandlerTest.php` | Unit tests for `!koboldo` |

### Files to Modify

| Path | Change |
|---|---|
| `composer.json` | Add `"team-reflex/discord-php": "^10.0"` to `require` |
| `app/Providers/AppServiceProvider.php` | Bind `Discord` client and `DiscordBotService` in the container |
| `.env.example` (create if absent) | Add `DISCORD_BOT_TOKEN=` entry |

### Files to Delete

None.

---

## Laravel Zero Specifics

### CLI Command

**File**: `app/Commands/BotRunCommand.php`

**Signature**: `bot:run`

No arguments or options for this feature. The command is purely a process runner.

```php
<?php

namespace App\Commands;

use App\Services\DiscordBotService;
use LaravelZero\Framework\Commands\Command;

class BotRunCommand extends Command
{
    protected $signature = 'bot:run';

    protected $description = 'Start the Discord bot and connect to the gateway';

    public function handle(DiscordBotService $botService): void
    {
        $this->info('Starting Discord bot...');
        $botService->start();
    }
}
```

### Service

**File**: `app/Services/DiscordBotService.php`

```php
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
        $this->discord->on(Event::MESSAGE_CREATE, function ($message) {
            $this->registry->dispatch($message, $this->discord);
        });

        $this->discord->run();
    }
}
```

### Handler Registry

**File**: `app/Handlers/MessageHandlerRegistry.php`

```php
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
            if ($content === $prefix || str_starts_with($content, $prefix . ' ')) {
                $handler($message, $discord);
                return;
            }
        }
    }
}
```

### Message Handlers

**File**: `app/Handlers/KciaoHandler.php`

```php
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
```

**File**: `app/Handlers/KaiutoHandler.php`

```php
<?php

namespace App\Handlers;

use Discord\Discord;
use Discord\Parts\Channel\Message;

class KaiutoHandler
{
    public function __invoke(Message $message, Discord $discord): void
    {
        $help = <<<TEXT
            **Comandi disponibili:**
            `!kciao` — Il bot ti saluta
            `!kaiuto` — Mostra questo messaggio di aiuto
            `!koboldo` — Evoca Koboldo
            TEXT;

        $message->channel->sendMessage($help);
    }
}
```

**File**: `app/Handlers/KobolodoHandler.php`

```php
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
```

### AppServiceProvider Bindings

**File**: `app/Providers/AppServiceProvider.php` — update `register()`:

```php
public function register(): void
{
    $this->app->singleton(\Discord\Discord::class, function () {
        return new \Discord\Discord([
            'token' => config('discord.token'),
        ]);
    });

    $this->app->singleton(\App\Handlers\MessageHandlerRegistry::class, function ($app) {
        $registry = new \App\Handlers\MessageHandlerRegistry();
        $registry->register('!kciao',   $app->make(\App\Handlers\KciaoHandler::class));
        $registry->register('!kaiuto',  $app->make(\App\Handlers\KaiutoHandler::class));
        $registry->register('!koboldo', $app->make(\App\Handlers\KobolodoHandler::class));
        return $registry;
    });

    $this->app->singleton(\App\Services\DiscordBotService::class);
}
```

### Optional Components

No additional Laravel Zero optional components (e.g., `app:install http`, `app:install database`) are required for this feature.

### Scheduling

No scheduled tasks are introduced. `BotRunCommand` is a blocking process, not a cron-triggered command.

---

## Validation Rules

The `bot:run` command takes no arguments or options, so there is no user-supplied input to validate at the Laravel Zero layer.

At startup, the service should fail fast if `DISCORD_BOT_TOKEN` is missing or empty:

```php
// In DiscordBotService::start() or via AppServiceProvider binding
if (empty(config('discord.token'))) {
    throw new \RuntimeException('DISCORD_BOT_TOKEN is not set. Add it to your .env file.');
}
```

DiscordPHP itself will throw an `InvalidTokenException` if the token is syntactically invalid or rejected by the Discord API. The command should let that exception propagate (Laravel Zero will display it and exit non-zero).

---

## Authorization & Security

### Token Handling

- The bot token is read exclusively from `config('discord.token')` (backed by `.env`).
- The token must **never** be committed to version control. Ensure `.gitignore` includes `.env`.
- Do not log the token at any verbosity level.

### Discord Permission Model

- The bot requires the **Message Content Intent** to be enabled in the Discord Developer Portal (Applications → Bot → Privileged Gateway Intents). Without this, `$message->content` will be an empty string for non-slash-command messages.
- The bot must be invited to a guild with at minimum the `Send Messages` and `Read Message History` OAuth2 scopes/permissions.

### Who Can Trigger Commands

Any user in any channel the bot can read may trigger `!kciao`, `!kaiuto`, and `!koboldo`. No per-user, per-role, or per-guild restrictions are imposed in this feature.

### Rate Limiting

DiscordPHP's built-in ReactPHP HTTP client handles Discord's rate-limit headers automatically. No additional application-level rate limiting is implemented in this feature.

---

## Testing

### Test Conventions

- Feature tests live in `tests/Feature/`, extend `Tests\TestCase`, and are bound via `uses(Tests\TestCase::class)->in('Feature')` in `tests/Pest.php`.
- Unit tests live in `tests/Unit/`, do not need the base class.
- Command invocation uses `$this->artisan('bot:run')`.
- The `Discord` client **must be mocked** in tests — it must never make real network calls.

### Mocking Strategy

Bind a mock `Discord` instance in `AppServiceProvider` before the command runs, and mock `DiscordBotService::start()` to return immediately (avoiding the blocking event loop):

```php
// In tests/Feature/BotRunCommandTest.php
beforeEach(function () {
    $this->mock(\App\Services\DiscordBotService::class)
         ->shouldReceive('start')
         ->once();
});
```

### Test Cases

#### Feature: `BotRunCommandTest`

```php
<?php

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->mock(\App\Services\DiscordBotService::class)
         ->shouldReceive('start')
         ->once();
});

it('starts the bot and exits successfully', function () {
    $this->artisan('bot:run')->assertExitCode(0);
});

it('prints a startup message before delegating to the service', function () {
    $this->artisan('bot:run')
         ->expectsOutput('Starting Discord bot...')
         ->assertExitCode(0);
});
```

#### Unit: `MessageHandlerRegistryTest`

```php
<?php

use App\Handlers\MessageHandlerRegistry;
use Discord\Discord;
use Discord\Parts\Channel\Message;

it('dispatches to the registered handler when prefix matches exactly', function () {
    $registry = new MessageHandlerRegistry();
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = Mockery::mock(Message::class);
    $message->shouldReceive('getAttribute')->with('content')->andReturn('!kciao');
    $message->content = '!kciao';

    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeTrue();
});

it('dispatches when message starts with prefix followed by a space', function () {
    $registry = new MessageHandlerRegistry();
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = Mockery::mock(Message::class);
    $message->content = '!kciao world';

    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeTrue();
});

it('does not dispatch when no prefix matches', function () {
    $registry = new MessageHandlerRegistry();
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = Mockery::mock(Message::class);
    $message->content = '!unknown';

    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeFalse();
});

it('does not dispatch on partial prefix match without space boundary', function () {
    $registry = new MessageHandlerRegistry();
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = Mockery::mock(Message::class);
    $message->content = '!kciaoExtra';

    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeFalse();
});

it('dispatches only the first matching handler and stops', function () {
    $registry = new MessageHandlerRegistry();
    $firstCalled = false;
    $secondCalled = false;

    $registry->register('!k', function () use (&$firstCalled) {
        $firstCalled = true;
    });
    $registry->register('!kciao', function () use (&$secondCalled) {
        $secondCalled = true;
    });

    $message = Mockery::mock(Message::class);
    $message->content = '!kciao';

    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    // Only the first matching handler fires
    expect($firstCalled || $secondCalled)->toBeTrue();
    expect($firstCalled && $secondCalled)->toBeFalse();
});
```

#### Unit: Handler Tests (one per handler file)

```php
// tests/Unit/Handlers/KciaoHandlerTest.php
use App\Handlers\KciaoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

it('sends a greeting in Italian', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::pattern('/Ciao/i'));

    $message = Mockery::mock(Message::class);
    $message->channel = $channel;

    $discord = Mockery::mock(Discord::class);

    (new KciaoHandler())($message, $discord);
});
```

```php
// tests/Unit/Handlers/KaiutoHandlerTest.php
use App\Handlers\KaiutoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

it('sends a help message listing all commands', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::allOf(
                Mockery::pattern('/!kciao/'),
                Mockery::pattern('/!kaiuto/'),
                Mockery::pattern('/!koboldo/'),
            ));

    $message = Mockery::mock(Message::class);
    $message->channel = $channel;

    $discord = Mockery::mock(Discord::class);

    (new KaiutoHandler())($message, $discord);
});
```

```php
// tests/Unit/Handlers/KobolodoHandlerTest.php
use App\Handlers\KobolodoHandler;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

it('sends "Koboldo in arrivo!" to the channel', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
            ->once()
            ->with('Koboldo in arrivo!');

    $message = Mockery::mock(Message::class);
    $message->channel = $channel;

    $discord = Mockery::mock(Discord::class);

    (new KobolodoHandler())($message, $discord);
});
```

---

## Success Criteria

- [ ] `composer require team-reflex/discord-php:^10.0` succeeds and the library is in `vendor/`.
- [ ] `config/discord.php` exists and `config('discord.token')` returns the value of `DISCORD_BOT_TOKEN`.
- [ ] `./application bot:run` appears in the command list (`./application`).
- [ ] Running `./application bot:run` without `DISCORD_BOT_TOKEN` set throws a clear `RuntimeException` and exits non-zero.
- [ ] With a valid token, `./application bot:run` connects to the Discord gateway (verified manually).
- [ ] Posting `!kciao` in a Discord channel causes the bot to reply with an Italian greeting.
- [ ] Posting `!kaiuto` causes the bot to reply with a formatted list of all three commands in Italian.
- [ ] Posting `!koboldo` causes the bot to reply with `Koboldo in arrivo!`.
- [ ] Unknown prefixes (e.g., `!ping`) produce no bot response.
- [ ] Messages that partially match a prefix without a word boundary (e.g., `!kciaoextra`) are not dispatched.
- [ ] All Pest tests pass: `./vendor/bin/pest` exits 0.
- [ ] Laravel Pint reports no style violations: `./vendor/bin/pint --test`.

---

## Future Considerations

These extensions are explicitly out of scope for this story but are natural next steps:

- **KoboldAI integration**: The `!koboldo` command is intentionally bare so that a future story can wire it to the KoboldAI API without touching the other handlers.
- **Emoji reactions**: The draft mentioned reacting to messages with emojis (`$message->react('👋')`); this can be added to any handler without architectural changes.
- **Per-guild command restriction**: Add a guild/channel allowlist read from `config/discord.php` and checked in `MessageHandlerRegistry::dispatch()`.
- **Slash commands**: Migrate prefix commands to Discord application commands (registered via REST) for a richer UX and better permission model.
- **`.env` validation command**: A `bot:check` command that validates the token format and required Discord intents are enabled, useful for onboarding.
