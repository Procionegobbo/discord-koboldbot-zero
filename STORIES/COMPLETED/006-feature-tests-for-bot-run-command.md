## Feature Tests for `bot:run` Command

**As a** developer
**I want** Pest feature tests for the `bot:run` command that mock the Discord service
**So that** the command's behaviour is verified in CI without making real network calls to Discord

**Acceptance Criteria:**
- [ ] `tests/Feature/BotRunCommandTest.php` exists and uses `Tests\TestCase`
- [ ] A `beforeEach` block mocks `\App\Services\DiscordBotService` and asserts `start()` is called exactly once per test
- [ ] Test `it('starts the bot and exits successfully')` asserts exit code 0
- [ ] Test `it('prints a startup message before delegating to the service')` asserts the output contains `Starting Discord bot...` and exit code is 0
- [ ] Running `./vendor/bin/pest tests/Feature/BotRunCommandTest.php` exits 0 with both tests passing
- [ ] The mock never touches a real Discord connection during the test run
- [ ] `./vendor/bin/pint --test` reports no style violations on the test file

**Story Points:** 2
**Priority:** High
**Dependencies:** 003-bot-run-command-and-discord-bot-service
**Notes:** Use `$this->mock(\App\Services\DiscordBotService::class)->shouldReceive('start')->once()` in `beforeEach`. Command invocation uses `$this->artisan('bot:run')`. The test file must begin with `uses(Tests\TestCase::class)` per project Pest conventions (`tests/Pest.php` binds `Tests\TestCase::class` for the `Feature` directory). No real `DISCORD_BOT_TOKEN` is needed because `DiscordBotService::start()` is fully mocked.
