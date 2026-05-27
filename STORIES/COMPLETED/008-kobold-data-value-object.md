## Implement KoboldData Value Object

**As a** developer
**I want** a typed, immutable value object that represents a generated kobold character
**So that** API response data can be passed around the codebase in a structured and type-safe way

**Acceptance Criteria:**
- [ ] `app/Data/KoboldData.php` exists with namespace `App\Data`
- [ ] `KoboldData` is declared `readonly class` with nine public constructor properties: `name`, `surname`, `origin`, `color`, `specialTrait`, `kinship`, `dish`, `story`, `job` (all `string`)
- [ ] `KoboldData::fromApiResponse(array $payload): self` maps the API keys `KoboldName`, `KoboldSurname`, `KoboldOrigin`, `KoboldColor`, `SpecialTrait`, `KoboldKinship`, `KoboldDish`, `KoboldStory`, `KoboldJob` to the corresponding properties
- [ ] Passing an array missing any of the nine expected keys causes `fromApiResponse` to throw (an `\InvalidArgumentException` or equivalent from the missing named argument)
- [ ] The class has no Eloquent, database, or cache dependencies

**Story Points:** 1
**Priority:** High
**Dependencies:** None
**Notes:** Exact class signature from the spec:
```php
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
    public static function fromApiResponse(array $payload): self { ... }
}
```
The factory uses named arguments so any missing key throws automatically.
