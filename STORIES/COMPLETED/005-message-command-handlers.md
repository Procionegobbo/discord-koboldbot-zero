## Message Command Handlers (`!kciao`, `!kaiuto`, `!koboldo`)

**As a** Discord user
**I want** to type `!kciao`, `!kaiuto`, or `!koboldo` in any channel the bot can read
**So that** the bot responds with the appropriate Italian-language message for each command

**Acceptance Criteria:**
- [ ] `app/Handlers/KciaoHandler.php` exists; invoking it sends a message containing `Ciao` (case-insensitive) to `$message->channel`
- [ ] `app/Handlers/KaiutoHandler.php` exists; invoking it sends a message that contains `!kciao`, `!kaiuto`, and `!koboldo`
- [ ] `app/Handlers/KobolodoHandler.php` exists; invoking it sends exactly `Koboldo in arrivo!` to `$message->channel`
- [ ] All three handlers are invocable classes (implement `__invoke(Message $message, Discord $discord): void`)
- [ ] Unit test `tests/Unit/Handlers/KciaoHandlerTest.php` passes: `./vendor/bin/pest tests/Unit/Handlers/KciaoHandlerTest.php` exits 0
- [ ] Unit test `tests/Unit/Handlers/KaiutoHandlerTest.php` passes: `./vendor/bin/pest tests/Unit/Handlers/KaiutoHandlerTest.php` exits 0
- [ ] Unit test `tests/Unit/Handlers/KobolodoHandlerTest.php` passes: `./vendor/bin/pest tests/Unit/Handlers/KobolodoHandlerTest.php` exits 0
- [ ] `./vendor/bin/pint --test` reports no style violations on all handler and test files

**Story Points:** 2
**Priority:** High
**Dependencies:** 001-install-discordphp-and-configure-token
**Notes:** Each handler lives in `app/Handlers/` and uses namespace `App\Handlers`. They call `$message->channel->sendMessage(...)` — no return value is used. `KciaoHandler` sends `'Ciao! Sono Koboldo, il tuo bot preferito! 🤖'`. `KaiutoHandler` sends a heredoc listing all three commands with Markdown code formatting. `KobolodoHandler` sends the literal string `'Koboldo in arrivo!'`. Tests use Mockery to mock `Channel`, `Message`, and `Discord`; the channel mock asserts `sendMessage` is called exactly once with the expected content. Handler test files live in `tests/Unit/Handlers/`.
