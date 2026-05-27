<?php

use App\Services\DiscordBotService;

beforeEach(function () {
    $this->mock(DiscordBotService::class)
        ->shouldReceive('start')
        ->once();
});

it('starts the bot and exits successfully', function () {
    $this->artisan('bot:run')->assertExitCode(0);
});

it('prints a startup message before delegating to the service', function () {
    $this->artisan('bot:run')
        ->expectsOutput('Starting Discord bot...')
        ->assertExitCode(0);
});
