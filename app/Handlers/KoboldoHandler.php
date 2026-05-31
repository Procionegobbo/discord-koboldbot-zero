<?php

namespace App\Handlers;

use App\Data\KoboldData;
use App\Services\KaasClient;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Throwable;

class KoboldoHandler
{
    use HasKoboldErrors;

    public function __construct(private readonly KaasClient $kaas) {}

    public function __invoke(Message $message, Discord $discord): void
    {
        $channel = $message->channel;

        $channel->sendMessage('Kobold in evocazione...');

        $this->kaas->generate()
            ->then(function (string $json) use ($channel): void {
                $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                $kobold = KoboldData::fromApiResponse($payload);
                $channel->sendMessage($this->formatSheet($kobold));
            })
            ->otherwise(function (Throwable $e) use ($channel): void {
                $channel->sendMessage($this->errorMessage($e));
            });
    }

    private function formatSheet(KoboldData $k): string
    {
        return <<<MD
            ## Koboldo Evocato!
            **Nome:** {$k->name} {$k->surname}
            **Origine:** {$k->origin}
            **Colore:** {$k->color}
            **Tratto Speciale:** {$k->specialTrait}
            **Parentela:** {$k->kinship}
            **Piatto Preferito:** {$k->dish}
            **Mestiere:** {$k->job}
            **Storia:** {$k->story}
            MD;
    }
}
