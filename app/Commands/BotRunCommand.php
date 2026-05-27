<?php

namespace App\Commands;

use App\Services\DiscordBotService;
use LaravelZero\Framework\Commands\Command;

class BotRunCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'bot:run';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start the Discord bot and connect to the gateway';

    /**
     * Execute the console command.
     */
    public function handle(DiscordBotService $botService): void
    {
        $this->info('Starting Discord bot...');

        $botService->start();
    }
}
