## Write Unit Tests for KaasClient

**As a** developer
**I want** unit tests that verify `KaasClient` handles all HTTP response scenarios correctly
**So that** the HTTP service layer is independently validated without real network calls

**Acceptance Criteria:**
- [ ] `tests/Unit/Services/KaasClientTest.php` exists and uses PestPHP `it()` syntax
- [ ] Test: HTTP 200 — promise resolves with the raw response body string
- [ ] Test: HTTP 429 — promise rejects with `\RuntimeException` whose message is `'rate_limit'`
- [ ] Test: HTTP 503 — promise rejects with `\RuntimeException` whose message is `'http_error:503'`
- [ ] Test: network-level rejection — the original exception propagates unchanged (identity check)
- [ ] All tests use `Mockery::mock(Browser::class)` for the HTTP double; no real network calls are made
- [ ] `fakeResponse(int $status, string $body): ResponseInterface` helper is defined in the test file
- [ ] All four tests pass with `./vendor/bin/pest tests/Unit/Services/KaasClientTest.php`

**Story Points:** 2
**Priority:** High
**Dependencies:** 009-kaas-client-service
**Notes:** `React\Promise\resolve()` and `React\Promise\reject()` from `react/promise` v3 resolve `.then()` / `.otherwise()` callbacks synchronously when the promise is already settled, so assertions can be made synchronously without running the event loop. Use a `$resolved = null` / `$caught = null` capture variable pattern. The `Browser` class is concrete (no interface), so `Mockery::mock(Browser::class)` generates a subclass double. Full test implementations are provided verbatim in the spec under "Test cases for `tests/Unit/Services/KaasClientTest.php`".
