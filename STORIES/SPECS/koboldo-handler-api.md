# KoboldoHandler API Integration — Implementation Specification

## Feature Name & Description

**Feature:** Replace the stub `KoboldoHandler` with a fully wired implementation that calls the
KaaS (Kobold As A Service) API, parses the response, and posts a formatted character sheet to the
Discord channel.

**Trigger:** A Discord user sends `!koboldo` (exact match, or `!koboldo ` followed by any text —
matching follows the existing `MessageHandlerRegistry` dispatch rules).

---

## Architecture / Design Overview

### ReactPHP Event Loop — Critical Constraint

`team-reflex/discord-php` is built entirely on top of the ReactPHP event loop
(`react/event-loop`). The `Discord::run()` call in `DiscordBotService::start()` **never returns**;
it hands control to the loop. Any blocking I/O inside a message handler will stall the entire loop,
freeze the WebSocket heartbeat, and cause the bot to disconnect.

**Rule: no blocking HTTP inside a handler.** The implementation must use
`React\Http\Browser::post()`, which returns a `React\Promise\PromiseInterface` and resolves
inside the same event loop without blocking.

### Chosen HTTP Client

`react/http` v1.11.0 is already installed as a transitive dependency of `discord-php/http`
(which is itself pulled in by `team-reflex/discord-php`). No additional `composer require` is
needed.

The `React\Http\Browser` class provides the asynchronous POST method required. The Browser can
be constructed without an explicit `LoopInterface`; it picks up the global `React\EventLoop\Loop`
instance, which is the same loop that Discord uses. Alternatively, the Discord instance exposes
`Discord::getLoop(): LoopInterface` and this can be injected if an explicit loop reference is
preferred.

### Sequence of Calls

```
Discord event loop
  └── MESSAGE_CREATE event fires
        └── MessageHandlerRegistry::dispatch()
              └── KoboldoHandler::__invoke($message, $discord)
                    ├── $channel->sendMessage('Kobold in evocazione...')  // immediate ack
                    └── $browser->post(KAAS_URL, headers, body)           // returns Promise
                          ├── ->then(onFulfilled)
                          │     └── parse JSON → format message → $channel->sendMessage(sheet)
                          └── ->otherwise(onRejected)
                                └── $channel->sendMessage(error string)
```

The handler returns `void` synchronously. Promise callbacks execute later within the loop when the
HTTP response arrives — no blocking occurs.

### New Service: `KaasClient`

A dedicated `KaasClient` class wraps `React\Http\Browser` and owns the HTTP concern. This keeps
`KoboldoHandler` focused on Discord messaging and makes the HTTP layer independently testable and
mockable.

---

## Configuration

Add the KaaS endpoint URL to the existing `config/discord.php`, with the production URL as the
default value so no `.env` entry is required in normal operation:

```php
'kaas_url' => env('KAAS_URL', 'https://kaas.procionegobbo.it/api/generate-kobold'),
```

`KaasClient` must read the URL from `config('discord.kaas_url')` rather than from a class
constant. This makes the endpoint overridable per environment (e.g., a mock server in CI)
without code changes.

---

## Data Model

The feature is **stateless**. No database tables, no cache writes, no queued jobs. Each `!koboldo`
invocation is an independent HTTP round-trip. The API response is consumed once and discarded.

**KoboldData value object** (plain PHP readonly class, no Eloquent):

```php
namespace App\Data;

readonly class KoboldData
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $origin,
        public string $color,
        public string $specialTrait,
        public string $kinship,
        public string $dish,
        public string $story,
        public string $job,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromApiResponse(array $payload): self
    {
        return new self(
            name:        $payload['KoboldName'],
            surname:     $payload['KoboldSurname'],
            origin:      $payload['KoboldOrigin'],
            color:       $payload['KoboldColor'],
            specialTrait:$payload['SpecialTrait'],
            kinship:     $payload['KoboldKinship'],
            dish:        $payload['KoboldDish'],
            story:       $payload['KoboldStory'],
            job:         $payload['KoboldJob'],
        );
    }
}
```

If any key is missing from the API payload the `fromApiResponse` factory will throw an
`\InvalidArgumentException` (from the named argument mismatch / missing array key). This is
intentional — it is caught by `KoboldoHandler` and triggers the parse-failure error path.

---

## Impact on Existing Code

### Files to create

| Path | Purpose |
|---|---|
| `app/Data/KoboldData.php` | Value object, as shown above |
| `app/Services/KaasClient.php` | Wraps `React\Http\Browser`; returns a Promise |
| `tests/Unit/Handlers/KoboldoHandlerTest.php` | Replace the current file entirely |
| `tests/Unit/Services/KaasClientTest.php` | New unit tests for `KaasClient` |

### Files to modify

| Path | Change |
|---|---|
| `app/Handlers/KoboldoHandler.php` | Replace stub with real implementation |
| `app/Providers/AppServiceProvider.php` | Bind `KaasClient` as singleton and inject into `KoboldoHandler` |

### Files to delete

None.

---

## Laravel Zero Specifics

### No additional `composer require`

`react/http` is already on disk (`vendor/react/http`). No install step is needed.

### Service binding in `AppServiceProvider::register()`

```php
use App\Services\KaasClient;
use React\Http\Browser;

// Inside register():
$this->app->singleton(KaasClient::class, function () {
    return new KaasClient(new Browser);
});

// Update the KoboldoHandler binding in the registry closure:
$registry->register('!koboldo', $app->make(KoboldoHandler::class));
// KoboldoHandler receives KaasClient through constructor auto-wiring.
```

`KoboldoHandler` has a constructor dependency on `KaasClient`. Because all handlers are resolved
via `$app->make()`, Laravel's container handles the injection automatically once `KaasClient` is
bound.

### `KaasClient` implementation

```php
namespace App\Services;

use React\Http\Browser;
use React\Promise\PromiseInterface;

class KaasClient
{
    public function __construct(private readonly Browser $browser) {}

    /** @return PromiseInterface<string> resolves with raw JSON body */
    public function generate(): PromiseInterface
    {
        return $this->browser->post(
            config('discord.kaas_url'),
            ['Content-Type' => 'application/json'],
            json_encode(['language' => 'it']),
        )->then(function ($response) {
            $status = $response->getStatusCode();

            if ($status === 429) {
                throw new \RuntimeException('rate_limit');
            }

            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException("http_error:{$status}");
            }

            return (string) $response->getBody();
        });
    }
}
```

### `KoboldoHandler` implementation

```php
namespace App\Handlers;

use App\Data\KoboldData;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Throwable;

class KoboldoHandler
{
    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Kobold in evocazione...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $kobold  = KoboldData::fromApiResponse($payload);
                $channel->sendMessage($this->formatSheet($kobold));
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }

    private function formatSheet(KoboldData $k): string
    {
        return <<<MD
            ## Koboldo Evocato!
            **Nome:** {$k->name} {$k->surname}
            **Origine:** {$k->origin}
            **Colore:** {$k->color}
            **Tratto Speciale:** {$k->specialTrait}
            **Parentela:** {$k->kinship}
            **Piatto Preferito:** {$k->dish}
            **Mestiere:** {$k->job}
            **Storia:** {$k->story}
            MD;
    }

    private function errorMessage(Throwable $e): string
    {
        return match (true) {
            str_starts_with($e->getMessage(), 'rate_limit')
                => 'Troppi kobold evocati! Riprova tra un secondo.',
            str_starts_with($e->getMessage(), 'http_error')
                => 'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.',
            $e instanceof \JsonException
                => 'Il messaggio del kobold era illeggibile. Riprova.',
            default
                => 'Evocazione fallita per cause misteriose. Riprova.',
        };
    }
}
```

---

## Discord Message Format

The character sheet is sent as a second message after the `'Kobold in evocazione...'` acknowledgement.
It uses Discord markdown heading (`##`) and bold labels (`**`).

### Template (exact output)

```
## Koboldo Evocato!
**Nome:** {KoboldName} {KoboldSurname}
**Origine:** {KoboldOrigin}
**Colore:** {KoboldColor}
**Tratto Speciale:** {SpecialTrait}
**Parentela:** {KoboldKinship}
**Piatto Preferito:** {KoboldDish}
**Mestiere:** {KoboldJob}
**Storia:** {KoboldStory}
```

All nine API fields are rendered. Field order follows a "character sheet" reading order: identity
first, appearance, lore, and story last. No field is omitted, truncated, or escaped — the API
values are embedded verbatim.

---

## Error Handling

| Condition | HTTP status / exception | Message sent to Discord |
|---|---|---|
| API rate limit exceeded | HTTP 429 | `Troppi kobold evocati! Riprova tra un secondo.` |
| Non-2xx response (not 429) | HTTP 4xx / 5xx | `Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.` |
| Network / connection failure | `\RuntimeException` from ReactPHP | `Evocazione fallita per cause misteriose. Riprova.` |
| Response is not valid JSON | `\JsonException` (from `JSON_THROW_ON_ERROR`) | `Il messaggio del kobold era illeggibile. Riprova.` |
| JSON valid but missing expected field | `\ErrorException` / TypeError from `KoboldData::fromApiResponse` | `Evocazione fallita per cause misteriose. Riprova.` (falls through to default) |

All errors are caught in the `.otherwise()` callback — the bot always sends a message and never
silently drops the event.

The acknowledgement message `'Kobold in evocazione...'` is sent synchronously before the HTTP call,
so the user always sees immediate feedback even if the HTTP call is slow or fails.

---

## Validation Rules

- No user-supplied input is used in the API call. The request body is always the hardcoded literal
  `{"language":"it"}`.
- No input validation on the `!koboldo` command arguments. Extra text after the command is
  silently ignored (existing `MessageHandlerRegistry` behaviour).
- API response values are embedded verbatim into the Discord message with no sanitisation. Discord
  itself enforces a 2000-character hard limit on messages; if a response exceeds this the
  `sendMessage` call will fail (Discord will reject it). This is an accepted risk for an internal
  tool; mitigation is listed under Future Considerations.

---

## Authorization & Security

- No authentication is required for the KaaS endpoint.
- The bot token is not used in the KaaS HTTP request.
- The KaaS URL comes from `config('discord.kaas_url')`; it is not user-controlled and is therefore
  not subject to SSRF.
- The JSON response is decoded with `json_decode` before use; no `eval` or dynamic code execution
  occurs.
- No Discord permissions beyond `MESSAGE_CONTENT` (already required) and `SEND_MESSAGES` are
  needed.

---

## Testing

### Conventions from the existing codebase

- PestPHP 3/4 with `it()` / `test()` syntax.
- Mockery for doubles; `Mockery::mock()` and `shouldReceive()`.
- `fakeMessage(array $attributes)` global helper (defined in `tests/Pest.php`) produces a
  `FakeMessage` test double.
- Unit tests live under `tests/Unit/`; no `uses(TestCase::class)` declaration is needed there
  (only `Feature` tests bind to `TestCase`).
- No test should make real network calls.

### Mocking strategy for the HTTP call

`KaasClient` is injected into `KoboldoHandler` via the constructor. In unit tests, pass a Mockery
mock of `KaasClient` directly — no HTTP is involved.

`React\Promise\resolve()` and `React\Promise\reject()` produce already-settled promises. Because
`react/promise` v3 (already installed) resolves `.then()` callbacks synchronously when the promise
is already settled, the test can assert synchronously without running the event loop.

```php
use React\Promise\resolve;
use React\Promise\reject;
```

### Test cases for `tests/Unit/Handlers/KoboldoHandlerTest.php`

```php
<?php

use App\Handlers\KoboldoHandler;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

// Helper: full valid API payload
function koboldPayload(): string
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

it('sends an acknowledgement immediately, then the character sheet on success', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (string $msg): bool =>
            str_contains($msg, 'Grix Baffi') &&
            str_contains($msg, 'Verde muschio') &&
            str_contains($msg, 'Minatore')
        ));

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve(koboldPayload()));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends the rate-limit error message on HTTP 429', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Troppi kobold evocati! Riprova tra un secondo.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new \RuntimeException('rate_limit')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a generic error message on network failure', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Evocazione fallita per cause misteriose. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new \RuntimeException('Connection refused')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a parse error message when the API returns malformed JSON', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il messaggio del kobold era illeggibile. Riprova.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\resolve('not valid json {{'));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});

it('sends a portal-closed error on non-429 HTTP error', function () {
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('sendMessage')->once()->with('Kobold in evocazione...');
    $channel->shouldReceive('sendMessage')
        ->once()
        ->with('Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.');

    $kaas = Mockery::mock(KaasClient::class);
    $kaas->shouldReceive('generate')
        ->once()
        ->andReturn(\React\Promise\reject(new \RuntimeException('http_error:503')));

    $message = fakeMessage(['channel' => $channel]);
    $discord = Mockery::mock(Discord::class);

    (new KoboldoHandler($kaas))($message, $discord);
});
```

### Test cases for `tests/Unit/Services/KaasClientTest.php`

These tests mock `React\Http\Browser` directly. Because `Browser` is a concrete class (not an
interface), use `Mockery::mock(Browser::class)` — Mockery generates a subclass double.

```php
<?php

use App\Services\KaasClient;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;

function fakeResponse(int $status, string $body): ResponseInterface
{
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn($status);
    $response->shouldReceive('getBody')->andReturn($body);
    return $response;
}

it('resolves with the response body on HTTP 200', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->with(
            'https://kaas.procionegobbo.it/api/generate-kobold',
            ['Content-Type' => 'application/json'],
            '{"language":"it"}'
        )
        ->andReturn(\React\Promise\resolve(fakeResponse(200, '{"KoboldName":"Grix"}')));

    $resolved = null;
    (new KaasClient($browser))->generate()->then(function (string $body) use (&$resolved): void {
        $resolved = $body;
    });

    expect($resolved)->toBe('{"KoboldName":"Grix"}');
});

it('rejects with rate_limit RuntimeException on HTTP 429', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\resolve(fakeResponse(429, '')));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (\Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBeInstanceOf(\RuntimeException::class)
        ->and($caught->getMessage())->toBe('rate_limit');
});

it('rejects with http_error RuntimeException on HTTP 503', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\resolve(fakeResponse(503, '')));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (\Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBeInstanceOf(\RuntimeException::class)
        ->and($caught->getMessage())->toBe('http_error:503');
});

it('propagates network-level rejections unchanged', function () {
    $networkError = new \RuntimeException('Connection refused');

    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('post')
        ->once()
        ->andReturn(\React\Promise\reject($networkError));

    $caught = null;
    (new KaasClient($browser))->generate()->otherwise(function (\Throwable $e) use (&$caught): void {
        $caught = $e;
    });

    expect($caught)->toBe($networkError);
});
```

### Running the tests

```bash
./vendor/bin/pest tests/Unit/Handlers/KoboldoHandlerTest.php
./vendor/bin/pest tests/Unit/Services/KaasClientTest.php
./vendor/bin/pest   # full suite
```

---

## Success Criteria

Binary pass/fail checklist. Every item must be green before the feature is considered done.

- [ ] `./vendor/bin/pest` passes with zero failures and zero errors.
- [ ] `tests/Unit/Handlers/KoboldoHandlerTest.php` contains all five cases listed above and all pass.
- [ ] `tests/Unit/Services/KaasClientTest.php` contains all four cases listed above and all pass.
- [ ] `KoboldoHandler::__invoke` has no blocking I/O; it returns `void` and defers all work to
      promise callbacks.
- [ ] Sending `!koboldo` to a running bot (manual smoke test) produces the `'Kobold in evocazione...'`
      acknowledgement within one second and the formatted character sheet a moment later.
- [ ] No new `composer require` was necessary (i.e., `composer.json` `require` block is unchanged).
- [ ] `app/Providers/AppServiceProvider.php` binds `KaasClient` as a singleton and `KoboldoHandler`
      is resolved via `$app->make()` so injection is container-managed.
- [ ] `config/discord.php` contains `kaas_url` with default `https://kaas.procionegobbo.it/api/generate-kobold`; `KaasClient` reads it via `config('discord.kaas_url')`.
- [ ] The request body sent to KaaS is `{"language":"it"}` with `Content-Type: application/json`.
- [ ] All nine API fields (`KoboldName`, `KoboldSurname`, `KoboldOrigin`, `KoboldColor`,
      `SpecialTrait`, `KoboldKinship`, `KoboldDish`, `KoboldStory`, `KoboldJob`) appear in the
      formatted Discord message.
- [ ] A simulated 429 response produces the Italian rate-limit message, not a stack trace or silence.
- [ ] `./vendor/bin/pint --test` reports no style violations.

---

## Future Considerations

- **Discord message length guard:** Truncate or split the character sheet if it exceeds Discord's
  2000-character limit.
- **Response caching:** Cache the last generated kobold per channel for a short TTL so a second
  rapid `!koboldo` does not consume an API call.
- **Retry on 429:** Implement a single automatic retry with a 1-second `$loop->addTimer()` delay
  rather than surfacing the error to the user.
- **`KaasClientInterface`:** Extract an interface from `KaasClient` to make the mock type-safe and
  decouple the handler from the concrete class.
- **Metrics / logging:** Log each API call (duration, status code) via a PSR-3 logger for
  operational visibility.
