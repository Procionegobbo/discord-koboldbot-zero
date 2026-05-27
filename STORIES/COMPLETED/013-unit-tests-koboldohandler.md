## Write Unit Tests for KoboldoHandler

**As a** developer
**I want** unit tests that verify `KoboldoHandler` sends the correct Discord messages for all outcomes
**So that** the handler's messaging logic is validated in isolation, without real HTTP or Discord connections

**Acceptance Criteria:**
- [ ] `tests/Unit/Handlers/KoboldoHandlerTest.php` exists (replacing the current stub file) and uses PestPHP `it()` syntax
- [ ] Test: success path — acknowledgement message sent first, then a character sheet containing `'Grix Baffi'`, `'Verde muschio'`, and `'Minatore'`
- [ ] Test: HTTP 429 — acknowledgement sent, then `'Troppi kobold evocati! Riprova tra un secondo.'`
- [ ] Test: network failure (`RuntimeException('Connection refused')`) — acknowledgement sent, then `'Evocazione fallita per cause misteriose. Riprova.'`
- [ ] Test: malformed JSON response — acknowledgement sent, then `'Il messaggio del kobold era illeggibile. Riprova.'`
- [ ] Test: non-429 HTTP error (`RuntimeException('http_error:503')`) — acknowledgement sent, then `'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.'`
- [ ] All tests use a Mockery mock of `KaasClient` injected via the constructor; no real HTTP calls are made
- [ ] All tests use `fakeMessage(['channel' => $channel])` (global helper from `tests/Pest.php`) to produce the Discord message double
- [ ] `koboldPayload(): string` helper returning a valid JSON payload with all nine fields is defined in the test file
- [ ] All five tests pass with `./vendor/bin/pest tests/Unit/Handlers/KoboldoHandlerTest.php`

**Story Points:** 3
**Priority:** High
**Dependencies:** 010-koboldohandler-implementation, 008-kobold-data-value-object
**Notes:** `React\Promise\resolve()` and `React\Promise\reject()` settle synchronously in `react/promise` v3, so no event loop is needed in tests. The mock `Channel` uses `shouldReceive('sendMessage')->once()->with(...)` to assert exact message content and call count. `Mockery::on(fn (string $msg): bool => ...)` is used for the character sheet assertion to check multiple substrings. Full test implementations are provided verbatim in the spec under "Test cases for `tests/Unit/Handlers/KoboldoHandlerTest.php`".
