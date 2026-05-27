<?php

namespace App\Data;

readonly class KoboldData
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $origin,
        public string $color,
        public string $specialTrait,
        public string $kinship,
        public string $dish,
        public string $story,
        public string $job,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromApiResponse(array $payload): self
    {
        return new self(
            name: $payload['KoboldName'],
            surname: $payload['KoboldSurname'],
            origin: $payload['KoboldOrigin'],
            color: $payload['KoboldColor'],
            specialTrait: $payload['SpecialTrait'],
            kinship: $payload['KoboldKinship'],
            dish: $payload['KoboldDish'],
            story: $payload['KoboldStory'],
            job: $payload['KoboldJob'],
        );
    }
}
