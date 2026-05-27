<?php

namespace App\Providers;

use App\Handlers\KaiutoHandler;
use App\Handlers\KciaoHandler;
use App\Handlers\KobolodoHandler;
use App\Handlers\MessageHandlerRegistry;
use App\Services\DiscordBotService;
use Discord\Discord;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Discord::class, function () {
            $token = config('discord.token');

            if (empty($token)) {
                throw new RuntimeException('DISCORD_BOT_TOKEN is not set. Add it to your .env file.');
            }

            return new Discord([
                'token' => $token,
            ]);
        });

        $this->app->singleton(MessageHandlerRegistry::class, function (Application $app) {
            $registry = new MessageHandlerRegistry;
            $registry->register('!kciao', $app->make(KciaoHandler::class));
            $registry->register('!kaiuto', $app->make(KaiutoHandler::class));
            $registry->register('!koboldo', $app->make(KobolodoHandler::class));

            return $registry;
        });

        $this->app->singleton(DiscordBotService::class);
    }
}
