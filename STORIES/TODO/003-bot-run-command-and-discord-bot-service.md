## `bot:run` Command and DiscordBotService

**As a** bot operator
**I want** a `bot:run` Laravel Zero command that starts the long-running Discord gateway connection
**So that** I can launch the bot with a single CLI invocation and have it listen for Discord events until the process is terminated

**Acceptance Criteria:**
- [ ] `app/Commands/BotRunCommand.php` exists with `protected $signature = 'bot:run'`
- [ ] `./application bot:run` appears in the output of `./application` (command list)
- [ ] `BotRunCommand::handle()` prints `Starting Discord bot...` to stdout before delegating
- [ ] `BotRunCommand::handle()` calls `DiscordBotService::start()` exactly once via constructor injection
- [ ] `app/Services/DiscordBotService.php` exists and its `start()` method registers a `MESSAGE_CREATE` listener then calls `$discord->run()`
- [ ] Running `./application bot:run` without `DISCORD_BOT_TOKEN` set throws a `RuntimeException` with a message containing `DISCORD_BOT_TOKEN` and exits non-zero
- [ ] `./vendor/bin/pint --test` reports no style violations on both new files

**Story Points:** 3
**Priority:** Critical
**Dependencies:** 002-ioc-bindings-for-discord-client-and-services
**Notes:** `BotRunCommand` signature: `bot:run`, no arguments or options. `DiscordBotService` constructor receives `Discord $discord` and `MessageHandlerRegistry $registry`. The token guard (`if (empty(config('discord.token')))`) can live in `DiscordBotService::start()` or in the `AppServiceProvider` Discord singleton factory — place it wherever causes the process to exit non-zero before touching the network. `Event::MESSAGE_CREATE` is the DiscordPHP constant for the message event. The command should use `$this->info('Starting Discord bot...')`.
