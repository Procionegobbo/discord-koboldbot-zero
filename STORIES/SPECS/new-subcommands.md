# New Subcommands — Implementation Specification

## Feature Name & Description

Two new Discord message commands, `!kricetta` and `!kmestiere`, allow users to ask Kichand the
kobold wizard for a random recipe or a random profession. Each command calls the existing KaaS API
(the same endpoint used by `!koboldo`), extracts a single field from the response, and posts it to
the channel as a short, character-appropriate reply.

**Current state:** No `!kricetta` or `!kmestiere` handlers exist. The `KaiutoHandler` help text
lists only three commands (`!kciao`, `!kaiuto`, `!koboldo`). The `MessageHandlerRegistry` has no
entries for these prefixes.

**In scope:**
- `KricettaHandler` — responds to `!kricetta`, returns the `KoboldDish` field wrapped in a
  character-voice reply.
- `KmestiereHandler` — responds to `!kmestiere`, returns the `KoboldJob` field wrapped in a
  character-voice reply.
- Update `KaiutoHandler` to list both new commands.
- Update `AppServiceProvider` to register both handlers in the `MessageHandlerRegistry`.
- Unit tests for both handlers following the established pattern.

**Out of scope:**
- Changes to `KaasClient` or its contract.
- Changes to `KoboldData` value object.
- Caching, pagination, or any persistent storage.
- New composer dependencies.
- Any new Discord intents or permissions.

---

## Architecture / Design Overview

Both new handlers follow the exact same pattern as `KoboldoHandler`:

1. Send an immediate acknowledgement to the channel (synchronous, before any I/O).
2. Call `$this->kaas->generate()`, which returns a `React\Promise\PromiseInterface<string>`.
3. In the `.then()` callback: decode the JSON, extract the single relevant field from the
   `KoboldData` value object, and send a formatted reply.
4. In the `.otherwise()` callback: delegate to the shared `errorMessage()` private method and
   send the appropriate Italian-language error string.

The handlers are stateless invokables (no properties beyond the injected `KaasClient`).
`KaasClient` is resolved from the container as a singleton and injected via the constructor,
identical to `KoboldoHandler`.

### Dispatch flow

```
Discord event loop
  └── MESSAGE_CREATE event fires
        └── MessageHandlerRegistry::dispatch()
              ├── KricettaHandler::__invoke($message, $discord)  [on !kricetta]
              │     ├── $channel->sendMessage('Un momento, consulto i miei libri di cucina...')
              │     └── $kaas->generate()
              │           ├── ->then()  → extract $kobold->dish → sendMessage(formatted reply)
              │           └── ->otherwise() → sendMessage(error string)
              └── KmestiereHandler::__invoke($message, $discord)  [on !kmestiere]
                    ├── $channel->sendMessage('Vediamo cosa dice il registro dei mestieri...')
                    └── $kaas->generate()
                          ├── ->then()  → extract $kobold->job → sendMessage(formatted reply)
                          └── ->otherwise() → sendMessage(error string)
```

The `MessageHandlerRegistry` dispatches on the first matching prefix (exact match or
`prefix + ' '`); the existing dispatch rules require no change.

---

## Configuration

No new environment variables, feature flags, or config keys are introduced. Both handlers use
`config('discord.kaas_url')` and `config('discord.kaas_bypass_key')` via `KaasClient`, exactly
as `KoboldoHandler` does.

---

## Data Model

No persistence. No new tables, no cache writes. Both handlers consume the API response transiently
and discard it. The existing `KoboldData` value object already exposes the `$dish` and `$job`
properties needed.

---

## Impact on Existing Code

### Files to create

| Path | Purpose |
|---|---|
| `app/Handlers/KricettaHandler.php` | New handler for `!kricetta` |
| `app/Handlers/KmestiereHandler.php` | New handler for `!kmestiere` |
| `tests/Unit/Handlers/KricettaHandlerTest.php` | Unit tests for `KricettaHandler` |
| `tests/Unit/Handlers/KmestiereHandlerTest.php` | Unit tests for `KmestiereHandler` |

### Files to modify

| Path | Change |
|---|---|
| `app/Handlers/KaiutoHandler.php` | Add `!kricetta` and `!kmestiere` to the help text |
| `app/Providers/AppServiceProvider.php` | Register both new handlers in the `MessageHandlerRegistry` singleton closure; add `use` imports |
| `tests/Unit/Handlers/KaiutoHandlerTest.php` | Extend the existing assertion to also check for `!kricetta` and `!kmestiere` |

### Files to delete

None.

---

## Laravel Zero Specifics

### Handler implementations

#### `app/Handlers/KricettaHandler.php`

```php
<?php

namespace App\Handlers;

use App\Data\KoboldData;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use JsonException;
use Throwable;

class KricettaHandler
{
    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Un momento, consulto i miei libri di cucina...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $kobold = KoboldData::fromApiResponse($payload);
                $channel->sendMessage("Oggi consiglio: **{$kobold->dish}**! Un piatto degno di un vero coboldo.");
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }

    private function errorMessage(Throwable $e): string
    {
        return match (true) {
            str_starts_with($e->getMessage(), 'rate_limit') => 'Troppi kobold evocati! Riprova tra un secondo.',
            str_starts_with($e->getMessage(), 'http_error') => 'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.',
            $e instanceof JsonException => 'Il messaggio del kobold era illeggibile. Riprova.',
            default => 'Evocazione fallita per cause misteriose. Riprova.',
        };
    }
}
```

#### `app/Handlers/KmestiereHandler.php`

```php
<?php

namespace App\Handlers;

use App\Data\KoboldData;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use JsonException;
use Throwable;

class KmestiereHandler
{
    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Vediamo cosa dice il registro dei mestieri...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $kobold = KoboldData::fromApiResponse($payload);
                $channel->sendMessage("Il mestiere estratto è: **{$kobold->job}**! Un onorevole lavoro per ogni coboldo.");
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }

    private function errorMessage(Throwable $e): string
    {
        return match (true) {
            str_starts_with($e->getMessage(), 'rate_limit') => 'Troppi kobold evocati! Riprova tra un secondo.',
            str_starts_with($e->getMessage(), 'http_error') => 'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.',
            $e instanceof JsonException => 'Il messaggio del kobold era illeggibile. Riprova.',
            default => 'Evocazione fallita per cause misteriose. Riprova.',
        };
    }
}
```

### Updated `app/Handlers/KaiutoHandler.php`

Add both new commands to the help text. The updated `__invoke` body:

```php
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
```

### Updated `app/Providers/AppServiceProvider.php`

Add `use` imports at the top of the file alongside the existing handler imports:

```php
use App\Handlers\KmestiereHandler;
use App\Handlers\KricettaHandler;
```

Inside the `MessageHandlerRegistry` singleton closure, add registrations after the existing
`!koboldo` entry:

```php
$registry->register('!kricetta', $app->make(KricettaHandler::class));
$registry->register('!kmestiere', $app->make(KmestiereHandler::class));
```

The full updated `register()` section of the closure becomes:

```php
$this->app->singleton(MessageHandlerRegistry::class, function (Application $app) {
    $registry = new MessageHandlerRegistry;
    $registry->register('!kciao', $app->make(KciaoHandler::class));
    $registry->register('!kaiuto', $app->make(KaiutoHandler::class));
    $registry->register('!koboldo', $app->make(KoboldoHandler::class));
    $registry->register('!kricetta', $app->make(KricettaHandler::class));
    $registry->register('!kmestiere', $app->make(KmestiereHandler::class));

    return $registry;
});
```

Both `KricettaHandler` and `KmestiereHandler` receive `KaasClient` through the container's
constructor auto-wiring — `KaasClient` is already bound as a singleton, so no additional binding
is needed.

---

## Discord Message Format

### `!kricetta` — success reply

```
Oggi consiglio: **{KoboldDish}**! Un piatto degno di un vero coboldo.
```

The `KoboldDish` value is taken verbatim from the API response and embedded between the bold
markers. No additional markdown heading is used; the reply is intentionally short and casual.

### `!kmestiere` — success reply

```
Il mestiere estratto è: **{KoboldJob}**! Un onorevole lavoro per ogni coboldo.
```

Same pattern: single field, bold-wrapped, no heading.

### Acknowledgement messages

| Command | Acknowledgement sent before the HTTP call |
|---|---|
| `!kricetta` | `Un momento, consulto i miei libri di cucina...` |
| `!kmestiere` | `Vediamo cosa dice il registro dei mestieri...` |

The acknowledgement is sent synchronously before `$kaas->generate()` is called, so the user
always receives immediate feedback.

---

## Error Handling

Both handlers use the same error table as `KoboldoHandler`. The `errorMessage()` private method is
identical in both classes.

| Condition | Exception / status | Message sent to Discord |
|---|---|---|
| API rate limit | HTTP 429 → `RuntimeException('rate_limit')` | `Troppi kobold evocati! Riprova tra un secondo.` |
| Non-2xx response (not 429) | `RuntimeException('http_error:{status}')` | `Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.` |
| Network failure | `RuntimeException` (other) | `Evocazione fallita per cause misteriose. Riprova.` |
| Malformed JSON | `JsonException` | `Il messaggio del kobold era illeggibile. Riprova.` |
| Missing JSON field | `TypeError` / `ErrorException` from `KoboldData::fromApiResponse` | `Evocazione fallita per cause misteriose. Riprova.` (default branch) |

---

## Validation Rules

- No user-supplied input is forwarded to the API. The request body is always the hardcoded literal
  `{"language":"it"}`.
- Any text after `!kricetta` or `!kmestiere` is silently ignored (existing
  `MessageHandlerRegistry` dispatch behaviour).
- The `KoboldDish` and `KoboldJob` values from the API are embedded verbatim — no escaping or
  length truncation.

---

## Authorization & Security

- These commands are available to any Discord user who can post in the channel where the bot is
  active. There is no role-based restriction; this matches the behaviour of all existing commands.
- The bot token is not used in the KaaS HTTP request.
- The KaaS URL is not user-controlled (`config('discord.kaas_url')`).
- No new Discord permissions or intents are required.
- No new `composer require` is needed; `react/http` is already a transitive dependency.

---

## Testing

### Conventions

- PestPHP with `it()` syntax.
- Mockery for all doubles (`Mockery::mock()`, `shouldReceive()`).
- `fakeMessage(array $attributes)` global helper from `tests/Pest.php`.
- `koboldPayload()` helper already defined in `tests/Unit/Handlers/KoboldoHandlerTest.php`.
  Each new test file defines its own copy of this helper (Pest loads test files in isolation;
  global function definitions in one test file are not visible in another).
- `React\Promise\resolve()` and `React\Promise\reject()` settle synchronously in `react/promise`
  v3, so no event loop setup is needed.
- No `uses(TestCase::class)` declaration is needed for unit tests under `tests/Unit/`.

### `tests/Unit/Handlers/KricettaHandlerTest.php`

```php
<?php

use App\Handlers\KricettaHandler;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

function kricettaPayload(): string
{
    return json_encode([
        'KoboldName'    => 'Grix',
        'KoboldSurname' => 'Baffi',
        'KoboldOrigin'  => 'Caverne del Nord',
        'KoboldColor'   => 'Verde muschio',
        'SpecialTrait'  => 'Vede al buio',
        'KoboldKinship' => 'Clan dei Baffi',
        'KoboldDish'    => 'Zuppa di funghi',
        'KoboldStory'   => 'Nato sotto una roccia fortunata.',
        'KoboldJob'     => 'Minatore',
    ]);
}

it('sends an acknowledgement immediately, then the dish on success', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Un momento, consulto i miei libri di cucina...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (string $msg): bool => str_contains($msg, 'Zuppa di funghi')));

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(kricettaPayload()));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KricettaHandler($kaas))($message, $discord);
});

it('sends the rate-limit error message on HTTP 429', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Un momento, consulto i miei libri di cucina...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Troppi kobold evocati! Riprova tra un secondo.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('rate_limit')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KricettaHandler($kaas))($message, $discord);
});

it('sends a generic error message on network failure', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Un momento, consulto i miei libri di cucina...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('Connection refused')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KricettaHandler($kaas))($message, $discord);
});

it('sends a parse error message when the API returns malformed JSON', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Un momento, consulto i miei libri di cucina...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il messaggio del kobold era illeggibile. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve('not valid json {{'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KricettaHandler($kaas))($message, $discord);
});

it('sends a portal-closed error on non-429 HTTP error', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Un momento, consulto i miei libri di cucina...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('http_error:503')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KricettaHandler($kaas))($message, $discord);
});
```

### `tests/Unit/Handlers/KmestiereHandlerTest.php`

```php
<?php

use App\Handlers\KmestiereHandler;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

function kmestierePayload(): string
{
    return json_encode([
        'KoboldName'    => 'Grix',
        'KoboldSurname' => 'Baffi',
        'KoboldOrigin'  => 'Caverne del Nord',
        'KoboldColor'   => 'Verde muschio',
        'SpecialTrait'  => 'Vede al buio',
        'KoboldKinship' => 'Clan dei Baffi',
        'KoboldDish'    => 'Zuppa di funghi',
        'KoboldStory'   => 'Nato sotto una roccia fortunata.',
        'KoboldJob'     => 'Minatore',
    ]);
}

it('sends an acknowledgement immediately, then the job on success', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (string $msg): bool => str_contains($msg, 'Minatore')));

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(kmestierePayload()));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends the rate-limit error message on HTTP 429', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Troppi kobold evocati! Riprova tra un secondo.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('rate_limit')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a generic error message on network failure', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('Connection refused')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a parse error message when the API returns malformed JSON', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il messaggio del kobold era illeggibile. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve('not valid json {{'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});

it('sends a portal-closed error on non-429 HTTP error', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Vediamo cosa dice il registro dei mestieri...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new RuntimeException('http_error:503')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KmestiereHandler($kaas))($message, $discord);
});
```

### Updated `tests/Unit/Handlers/KaiutoHandlerTest.php`

Replace the existing `str_contains` check to also assert presence of both new commands:

```php
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
```

### Running the tests

```bash
# New handler tests individually
./vendor/bin/pest tests/Unit/Handlers/KricettaHandlerTest.php
./vendor/bin/pest tests/Unit/Handlers/KmestiereHandlerTest.php

# Updated help test
./vendor/bin/pest tests/Unit/Handlers/KaiutoHandlerTest.php

# Full suite
./vendor/bin/pest
```

---

## Success Criteria

- [ ] `./vendor/bin/pest` passes with zero failures and zero errors across the entire suite.
- [ ] `app/Handlers/KricettaHandler.php` exists and its `__invoke` method is non-blocking (returns `void`, defers all I/O to promise callbacks).
- [ ] `app/Handlers/KmestiereHandler.php` exists and its `__invoke` method is non-blocking (returns `void`, defers all I/O to promise callbacks).
- [ ] `tests/Unit/Handlers/KricettaHandlerTest.php` contains all five test cases listed above and all pass.
- [ ] `tests/Unit/Handlers/KmestiereHandlerTest.php` contains all five test cases listed above and all pass.
- [ ] Sending `!kricetta` to a running bot produces the acknowledgement message within one second, followed by a reply containing a bold-wrapped dish name.
- [ ] Sending `!kmestiere` to a running bot produces the acknowledgement message within one second, followed by a reply containing a bold-wrapped job name.
- [ ] `!kaiuto` output lists `!kricetta` and `!kmestiere` alongside the three existing commands.
- [ ] `tests/Unit/Handlers/KaiutoHandlerTest.php` asserts the presence of `!kricetta` and `!kmestiere` in the help text and passes.
- [ ] `app/Providers/AppServiceProvider.php` registers both new handlers in the `MessageHandlerRegistry` singleton closure using `$app->make()`.
- [ ] `composer.json` is unchanged (no new dependencies introduced).
- [ ] `./vendor/bin/pint --test` reports no style violations.

---

## Future Considerations

- **Shared `errorMessage` trait or base class:** The `errorMessage()` private method is now
  duplicated across `KoboldoHandler`, `KricettaHandler`, and `KmestiereHandler`. A protected
  base class (e.g. `AbstractKoboldHandler`) or a PHP trait could eliminate this repetition.
- **Shared `KaasClient` constructor injection pattern:** The constructor signature
  `public function __construct(private readonly KaasClient $kaas) {}` is also duplicated across
  the three handlers. The same base-class extraction would resolve this.
- **Dedicated acknowledgement strings:** If the character-voice messages need to be updated in
  the future, centralising them in a language file (or at minimum named class constants) would
  make them easier to manage.
- **Discord message length guard:** If the `KoboldDish` or `KoboldJob` value from the API were
  unusually long, the reply could approach Discord's 2000-character limit. Truncation is not
  implemented in this spec; it was similarly deferred in the `KoboldoHandler` spec.
