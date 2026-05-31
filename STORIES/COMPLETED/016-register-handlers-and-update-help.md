# 016 — Register New Handlers and Update Help Text

## User Story

**As a** Discord user,
**I want** `!kricetta` and `!kmestiere` to be recognized by the bot and listed in `!kaiuto`,
**So that** the new commands are discoverable and actually reachable via the message dispatch loop.

---

## Background

`KricettaHandler` (story 014) and `KmestiereHandler` (story 015) are invokable classes, but they are inert until wired into the application. Two integration points must be updated:

1. **`AppServiceProvider`** — the `MessageHandlerRegistry` singleton closure is the only place handlers are registered; without an entry here, the dispatcher never matches `!kricetta` or `!kmestiere` and silently ignores those messages.
2. **`KaiutoHandler`** — the help text currently lists only `!kciao`, `!kaiuto`, and `!koboldo`; users have no way to discover the new commands until they appear here.

The existing `KaiutoHandlerTest` must also be extended so that the test suite enforces the help text contract going forward.

This story has no new runtime logic of its own — it is purely wiring and copy changes. It depends on stories 014 and 015 being merged first.

---

## Acceptance Criteria

### `AppServiceProvider` — handler registration

- [ ] `app/Providers/AppServiceProvider.php` adds the following `use` imports alongside the existing handler imports:
  ```php
  use App\Handlers\KmestiereHandler;
  use App\Handlers\KricettaHandler;
  ```
- [ ] Inside the `MessageHandlerRegistry` singleton closure, the following two lines are added **after** the `!koboldo` registration:
  ```php
  $registry->register('!kricetta', $app->make(KricettaHandler::class));
  $registry->register('!kmestiere', $app->make(KmestiereHandler::class));
  ```
- [ ] The full registration block becomes (order preserved):
  ```php
  $registry->register('!kciao',      $app->make(KciaoHandler::class));
  $registry->register('!kaiuto',     $app->make(KaiutoHandler::class));
  $registry->register('!koboldo',    $app->make(KoboldoHandler::class));
  $registry->register('!kricetta',   $app->make(KricettaHandler::class));
  $registry->register('!kmestiere',  $app->make(KmestiereHandler::class));
  ```
- [ ] No other changes are made to `AppServiceProvider`.
- [ ] `KaasClient` does not need an additional binding — it is already registered as a singleton; `$app->make(KricettaHandler::class)` and `$app->make(KmestiereHandler::class)` resolve it via constructor auto-wiring.

### `KaiutoHandler` — help text

- [ ] `app/Handlers/KaiutoHandler.php` updated `__invoke` body sends a help message that includes all five commands in this exact wording:
  ```
  **Comandi disponibili:**
  `!kciao` — Il bot ti saluta
  `!kaiuto` — Mostra questo messaggio di aiuto
  `!koboldo` — Evoca Koboldo
  `!kricetta` — Ottieni una ricetta casuale dal mondo dei coboldi
  `!kmestiere` — Scopri un mestiere casuale dal mondo dei coboldi
  ```
- [ ] The existing three command lines (`!kciao`, `!kaiuto`, `!koboldo`) are preserved verbatim.
- [ ] No other logic in `KaiutoHandler` is changed.

### `KaiutoHandlerTest` — updated assertion

- [ ] `tests/Unit/Handlers/KaiutoHandlerTest.php` replaces the existing `str_contains` closure so that the single test case asserts the presence of **all five** commands:
  ```php
  return str_contains($content, '!kciao')
      && str_contains($content, '!kaiuto')
      && str_contains($content, '!koboldo')
      && str_contains($content, '!kricetta')
      && str_contains($content, '!kmestiere');
  ```
- [ ] The test case name remains `'sends a help message listing all commands'`.
- [ ] `./vendor/bin/pest tests/Unit/Handlers/KaiutoHandlerTest.php` passes with 1 passing, 0 failing.

### Full suite

- [ ] `./vendor/bin/pest` passes with zero failures and zero errors across all tests, including the 5 new `KricettaHandlerTest` cases, the 5 new `KmestiereHandlerTest` cases, and the updated `KaiutoHandlerTest`.
- [ ] No previously passing test is broken.

### No unintended changes

- [ ] `composer.json` and `composer.lock` are unchanged.
- [ ] No new Discord intents or permissions are introduced.
- [ ] No environment variables or config keys are added.

---

## Style

- [ ] `./vendor/bin/pint --test` reports no violations on:
  - `app/Providers/AppServiceProvider.php`
  - `app/Handlers/KaiutoHandler.php`
  - `tests/Unit/Handlers/KaiutoHandlerTest.php`

---

## Manual Verification

- [ ] Sending `!kricetta` to a running bot produces `Un momento, consulto i miei libri di cucina...` within one second, followed by a reply containing a bold-wrapped dish name.
- [ ] Sending `!kmestiere` to a running bot produces `Vediamo cosa dice il registro dei mestieri...` within one second, followed by a reply containing a bold-wrapped job name.
- [ ] Sending `!kaiuto` to a running bot shows a help message that lists all five commands.
- [ ] Any text appended after `!kricetta` or `!kmestiere` (e.g., `!kricetta extra`) is silently ignored and the command still executes normally (existing `MessageHandlerRegistry` dispatch behaviour).

---

## Story Points

**2**

## Priority

High

## Dependencies

- Story 014 (`KricettaHandler`) — `app/Handlers/KricettaHandler.php` must exist before this story can be merged.
- Story 015 (`KmestiereHandler`) — `app/Handlers/KmestiereHandler.php` must exist before this story can be merged.

## Out of Scope

- A shared base class or trait for the duplicated `errorMessage` method across the three handlers (future consideration noted in spec).
- Centralising acknowledgement strings in language files or class constants (future consideration noted in spec).
- Discord message length truncation for unusually long `KoboldDish` / `KoboldJob` values (deferred, same as `KoboldoHandler`).
