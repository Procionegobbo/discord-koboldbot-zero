<?php

namespace App\Handlers;

use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Throwable;

class KricettaHandler
{
    use HasKoboldErrors;

    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Un momento, consulto i miei libri di cucina...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $dish = $payload['KoboldDish'] ?? throw new \UnexpectedValueException('KoboldDish missing');
                $channel->sendMessage("Oggi consiglio: **{$dish}**! Un piatto degno di un vero coboldo.");
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }
}
