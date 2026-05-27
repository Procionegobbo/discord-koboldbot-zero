## Message Handler Registry

**As a** developer
**I want** a `MessageHandlerRegistry` class that routes incoming Discord messages to the correct handler based on prefix matching
**So that** adding new commands in the future requires only registering a new handler without modifying the service or other handlers

**Acceptance Criteria:**
- [ ] `app/Handlers/MessageHandlerRegistry.php` exists with `register(string $prefix, callable $handler)` and `dispatch(Message $message, Discord $discord)` methods
- [ ] `dispatch()` calls the registered handler when the message content equals the prefix exactly
- [ ] `dispatch()` calls the registered handler when the message content starts with `{prefix} ` (prefix followed by a space)
- [ ] `dispatch()` does not call any handler when no registered prefix matches
- [ ] `dispatch()` does not call a handler when the message matches a prefix string but lacks a word boundary (e.g., `!kciaoExtra` does not trigger `!kciao`)
- [ ] `dispatch()` calls only the first matching handler and returns immediately (no double-dispatch)
- [ ] All five `MessageHandlerRegistryTest` cases described in the spec pass: `./vendor/bin/pest tests/Unit/MessageHandlerRegistryTest.php` exits 0
- [ ] `./vendor/bin/pint --test` reports no style violations on the registry and its test file

**Story Points:** 3
**Priority:** Critical
**Dependencies:** 001-install-discordphp-and-configure-token
**Notes:** The `$handlers` property is `array<string, callable>`. The matching logic uses `$content === $prefix || str_starts_with($content, $prefix . ' ')` after `trim($message->content ?? '')`. Test file lives at `tests/Unit/MessageHandlerRegistryTest.php` and uses Mockery to mock `Message` and `Discord`. This story is independent of the command and service stories — it can be developed and tested in isolation.
