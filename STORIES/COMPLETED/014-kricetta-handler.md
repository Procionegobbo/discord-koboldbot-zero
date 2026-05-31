# 014 — KricettaHandler: `!kricetta` Discord Subcommand

## User Story

**As a** Discord user in a channel where the bot is active,
**I want** to type `!kricetta` and receive a random kobold dish recommendation,
**So that** I can get a short, character-voiced response from Kichand without receiving the full kobold character sheet.

---

## Background

The existing `!koboldo` command (`KoboldoHandler`) generates a full kobold character sheet via the KaaS API and renders all nine fields. The `KoboldDish` field already exists in the `KoboldData` value object and is populated by every API call. `KricettaHandler` reuses the same async flow — `KaasClient::generate()` → `.then()` → `.otherwise()` — but surfaces only the dish field in a single-line reply.

The `KoboldData` value object, `KaasClient` contract, and `MessageHandlerRegistry` dispatch mechanism are **unchanged by this story**.

---

## Acceptance Criteria

### Handler structure

- [ ] `app/Handlers/KricettaHandler.php` exists in the `App\Handlers` namespace.
- [ ] The class is a stateless invokable: `public function __invoke(Message $message, Discord $discord): void`.
- [ ] Constructor signature: `public function __construct(private readonly KaasClient $kaas) {}` — no other properties.
- [ ] The class uses `use` imports for `App\Data\KoboldData`, `App\Services\KaasClient`, `Discord\Discord`, `Discord\Parts\Channel\Message`, `JsonException`, and `Throwable`.

### Acknowledgement (synchronous, before any I/O)

- [ ] Before calling `$this->kaas->generate()`, the handler sends exactly:
  ```
  Un momento, consulto i miei libri di cucina...
  ```
  to `$message->channel` via `sendMessage()`.
- [ ] This call is made synchronously — it is not inside any promise callback.

### Success path

- [ ] The handler calls `$this->kaas->generate()` exactly once.
- [ ] In the `.then()` callback, the JSON string is decoded with:
  ```php
  json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR)
  ```
- [ ] `KoboldData::fromApiResponse($payload)` is called with the decoded array.
- [ ] The success message sent to the channel matches exactly:
  ```
  Oggi consiglio: **{KoboldDish}**! Un piatto degno di un vero coboldo.
  ```
  where `{KoboldDish}` is the verbatim value of `$kobold->dish`.
- [ ] No heading, no additional markdown beyond the bold markers around the dish name.

### Error path

- [ ] The `.otherwise()` callback delegates to a private `errorMessage(Throwable $e): string` method.
- [ ] `errorMessage` implements the following match table:

  | Condition | Message sent to Discord |
  |---|---|
  | `str_starts_with($e->getMessage(), 'rate_limit')` | `Troppi kobold evocati! Riprova tra un secondo.` |
  | `str_starts_with($e->getMessage(), 'http_error')` | `Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.` |
  | `$e instanceof JsonException` | `Il messaggio del kobold era illeggibile. Riprova.` |
  | default (any other `Throwable`) | `Evocazione fallita per cause misteriose. Riprova.` |

- [ ] The error string returned by `errorMessage` is sent to the channel via `sendMessage()` inside the `.otherwise()` callback.

### Non-blocking contract

- [ ] `__invoke` returns `void` and does not block; all I/O after the acknowledgement is deferred to promise callbacks.
- [ ] No `sleep()`, `await()`, or synchronous HTTP call is used.

### No side effects on existing code

- [ ] `KaasClient`, `KoboldData`, `MessageHandlerRegistry`, and all other existing handlers are **not modified** by this story.
- [ ] `composer.json` and `composer.lock` are unchanged.

---

## Unit Tests

File: `tests/Unit/Handlers/KricettaHandlerTest.php`

- [ ] Uses PestPHP `it()` syntax with no `uses(TestCase::class)` declaration.
- [ ] Defines a file-local helper `kricettaPayload(): string` returning a JSON-encoded array with all nine KoboldData fields (including `'KoboldDish' => 'Zuppa di funghi'`).
- [ ] Does **not** reuse the `koboldPayload()` function defined in `KoboldoHandlerTest.php`; defines its own copy.
- [ ] Uses `Mockery::mock()` and `shouldReceive()` for all test doubles; uses `fakeMessage()` global helper for the Discord message.
- [ ] All five test cases listed below are present and pass:

  **Test 1 — success path:**
  - Mocks `Channel` to expect `sendMessage('Un momento, consulto i miei libri di cucina...')` once.
  - Mocks `Channel` to expect `sendMessage` once with a string that `str_contains($msg, 'Zuppa di funghi')`.
  - Mocks `KaasClient::generate()` to return `React\Promise\resolve(kricettaPayload())`.
  - Invokes `(new KricettaHandler($kaas))($message, $discord)`.

  **Test 2 — HTTP 429 (rate limit):**
  - Mocks `KaasClient::generate()` to return `React\Promise\reject(new RuntimeException('rate_limit'))`.
  - Asserts second `sendMessage` receives `'Troppi kobold evocati! Riprova tra un secondo.'`.

  **Test 3 — network failure (generic RuntimeException):**
  - Mocks `KaasClient::generate()` to return `React\Promise\reject(new RuntimeException('Connection refused'))`.
  - Asserts second `sendMessage` receives `'Evocazione fallita per cause misteriose. Riprova.'`.

  **Test 4 — malformed JSON:**
  - Mocks `KaasClient::generate()` to return `React\Promise\resolve('not valid json {{')`.
  - Asserts second `sendMessage` receives `'Il messaggio del kobold era illeggibile. Riprova.'`.

  **Test 5 — non-429 HTTP error:**
  - Mocks `KaasClient::generate()` to return `React\Promise\reject(new RuntimeException('http_error:503'))`.
  - Asserts second `sendMessage` receives `'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.'`.

- [ ] `./vendor/bin/pest tests/Unit/Handlers/KricettaHandlerTest.php` reports 5 passing, 0 failing.

---

## Style

- [ ] `./vendor/bin/pint --test` reports no violations on `app/Handlers/KricettaHandler.php` and `tests/Unit/Handlers/KricettaHandlerTest.php`.

---

## Story Points

**3**

## Priority

High

## Dependencies

- Story 013 (`KoboldoHandler`) — establishes the handler pattern; no code dependency at runtime, but provides the structural template.
- `KoboldData` value object with `$dish` property must exist (already delivered).
- `KaasClient` singleton binding must exist (already delivered).
- `fakeMessage()` Pest helper must exist in `tests/Pest.php` (already delivered).

## Out of Scope

- Registration in `AppServiceProvider` (covered by story 016).
- Listing in `KaiutoHandler` help text (covered by story 016).
- Changes to `KaasClient` or `KoboldData`.
- Caching, pagination, or user input forwarding.
