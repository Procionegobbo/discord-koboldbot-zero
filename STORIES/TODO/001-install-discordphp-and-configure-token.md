## Install DiscordPHP and Configure Bot Token

**As a** developer
**I want** the `team-reflex/discord-php` library installed and a `DISCORD_BOT_TOKEN` environment variable wired into the Laravel Zero config system
**So that** every other Discord feature has a stable dependency foundation and the bot token is read securely from the environment

**Acceptance Criteria:**
- [ ] `composer.json` lists `"team-reflex/discord-php": "^10.0"` under `require` and the package is present in `vendor/`
- [ ] `config/discord.php` exists and returns `['token' => env('DISCORD_BOT_TOKEN')]`
- [ ] `config('discord.token')` resolves to the value of `DISCORD_BOT_TOKEN` at runtime
- [ ] `.env.example` contains the line `DISCORD_BOT_TOKEN=your-bot-token-here`
- [ ] `.env` is listed in `.gitignore` (token must never be committed)
- [ ] `./vendor/bin/pint --test` reports no style violations on the new config file

**Story Points:** 1
**Priority:** Critical
**Dependencies:** None
**Notes:** Run `composer require team-reflex/discord-php:^10.0`. Create `config/discord.php` — Laravel Zero auto-loads files in `config/`. No `AppServiceProvider` changes needed in this story. `.env.example` may need to be created from scratch if it does not yet exist.
