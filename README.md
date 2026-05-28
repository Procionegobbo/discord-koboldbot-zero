# Kichand the KoboldBot

A Discord bot powered by KoboldAI, built with [Laravel Zero](https://laravel-zero.com/) (PHP 8.4).

Kichand is an ancient kobold sorcerer specialized in summoning other kobolds. Invoke him in your Discord server and watch the chaos unfold.

---

## Commands

| Command | Description |
|---|---|
| `!kciao` | Kichand introduces himself |
| `!koboldo` | Summons a random kobold with full character sheet |
| `!kaiuto` | Shows all available commands |

---

## Requirements

- PHP 8.4
- Composer
- A Discord bot token
- A [KaaS](https://github.com/Procionegobbo/kobold-as-a-service) API endpoint

---

## Installation

```bash
git clone https://github.com/Procionegobbo/discord-koboldbot-zero.git
cd discord-koboldbot-zero
composer install
```

Copy the environment file and fill in your credentials:

```bash
cp .env.example .env
```

```env
DISCORD_TOKEN=your-discord-bot-token
KAAS_URL=https://your-kaas-api-url
KAAS_BYPASS_KEY=your-bypass-key
```

---

## Running

```bash
php application bot:run
```

### Docker / Coolify

A `Dockerfile` is included for containerised deployments. Build and run with:

```bash
docker build -t kichand-koboldbot .
docker run --env-file .env kichand-koboldbot
```

---

## Development

```bash
# Run tests
./vendor/bin/pest

# Lint
./vendor/bin/pint

# Build standalone PHAR
php application app:build
```

---

## Architecture

- **Entry point**: `./application` → `bootstrap/app.php`
- **Bot command**: `app/Commands/BotRunCommand.php`
- **Message handlers**: `app/Handlers/` — one class per `!k` command, registered via `MessageHandlerRegistry`
- **KaaS client**: `app/Services/KaasClient.php` — async HTTP via ReactPHP
- **Data transfer**: `app/Data/KoboldData.php`

---

## License

MIT
