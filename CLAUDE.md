# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Discord bot powered by KoboldAI, built on [Laravel Zero](https://laravel-zero.com/) — a PHP micro-framework for console applications. Currently a fresh scaffold; Discord and KoboldAI functionality has not yet been implemented.

## Commands

```bash
# Run the application
./application

# Run tests (Pest)
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/InspireCommandTest.php

# Run a specific test by description
./vendor/bin/pest --filter "inspires artisans"

# Lint with Laravel Pint
./vendor/bin/pint

# Build a standalone PHAR (requires box)
php application app:build
```

## Architecture

**Entry point**: `./application` (binary) → `bootstrap/app.php` → `LaravelZero\Framework\Application`

**Commands**: All classes in `app/Commands/` extending `LaravelZero\Framework\Commands\Command` are auto-discovered. Register commands in `config/commands.php` if they need to be added outside that path or hidden/removed. The default command (shown when no subcommand is given) is set in `config/commands.php`.

**Service providers**: `bootstrap/providers.php` lists providers; `AppServiceProvider` in `app/Providers/` is the main extension point for bindings.

**Testing**: Pest is configured in `tests/Pest.php`. Feature tests use `$this->artisan('command-name')` from the `Tests\TestCase` base class. Unit tests don't need the base class (plain Pest functions).

**Building**: `box.json` controls the standalone PHAR build — it bundles `app/`, `bootstrap/`, `config/`, `vendor/`, and `composer.json` with GZ compression.
