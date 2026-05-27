## IoC Bindings for Discord Client and Services

**As a** developer
**I want** the `Discord` client, `MessageHandlerRegistry`, and `DiscordBotService` registered as singletons in the Laravel Zero IoC container
**So that** the command and tests can resolve these classes without constructing them manually, and mocks can be swapped in without subclassing

**Acceptance Criteria:**
- [ ] `AppServiceProvider::register()` binds `\Discord\Discord::class` as a singleton constructed with `['token' => config('discord.token')]`
- [ ] `AppServiceProvider::register()` binds `\App\Handlers\MessageHandlerRegistry::class` as a singleton with `!kciao`, `!kaiuto`, and `!koboldo` pre-registered
- [ ] `AppServiceProvider::register()` binds `\App\Services\DiscordBotService::class` as a singleton
- [ ] `app()->make(\App\Services\DiscordBotService::class)` resolves without errors when `DISCORD_BOT_TOKEN` is set
- [ ] `./vendor/bin/pint --test` reports no style violations on `AppServiceProvider.php`

**Story Points:** 2
**Priority:** Critical
**Dependencies:** 001-install-discordphp-and-configure-token
**Notes:** Edit `app/Providers/AppServiceProvider.php`. The `Discord` singleton must use `config('discord.token')`. The `MessageHandlerRegistry` singleton must call `$registry->register()` for all three handlers using `$app->make()`. The `DiscordBotService` singleton can rely on automatic constructor injection after the other two are bound. Handler classes (`KciaoHandler`, `KaiutoHandler`, `KobolodoHandler`) must exist before this story is testable — they can be empty stubs with just the `__invoke` signature at this stage.
