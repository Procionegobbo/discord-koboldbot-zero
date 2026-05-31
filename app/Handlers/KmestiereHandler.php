<?php

namespace App\Handlers;

use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Throwable;

class KmestiereHandler
{
    use HasKoboldErrors;

    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Vediamo cosa dice il registro dei mestieri...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $job = $payload['KoboldJob'] ?? throw new \UnexpectedValueException('KoboldJob missing');
                $channel->sendMessage("Il mestiere estratto è: **{$job}**! Un onorevole lavoro per ogni coboldo.");
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }
}
