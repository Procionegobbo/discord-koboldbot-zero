## Implement KoboldoHandler with Async KaaS Integration

**As a** Discord user
**I want** the `!koboldo` command to generate and display a full kobold character sheet
**So that** I receive a richly formatted kobold card in the channel after invoking the command

**Acceptance Criteria:**
- [ ] `KoboldoHandler::__invoke(Message $message, Discord $discord): void` sends `'Kobold in evocazione...'` to the channel immediately upon invocation (before the HTTP call)
- [ ] On a successful API response, a second message is sent to the channel containing a Discord-markdown character sheet with all nine fields rendered
- [ ] The formatted message follows this exact template:
  ```
  ## Koboldo Evocato!
  **Nome:** {KoboldName} {KoboldSurname}
  **Origine:** {KoboldOrigin}
  **Colore:** {KoboldColor}
  **Tratto Speciale:** {SpecialTrait}
  **Parentela:** {KoboldKinship}
  **Piatto Preferito:** {KoboldDish}
  **Mestiere:** {KoboldJob}
  **Storia:** {KoboldStory}
  ```
- [ ] On HTTP 429, the error message `'Troppi kobold evocati! Riprova tra un secondo.'` is sent to the channel
- [ ] On non-429 HTTP error, the error message `'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.'` is sent
- [ ] On malformed JSON (`\JsonException`), the message `'Il messaggio del kobold era illeggibile. Riprova.'` is sent
- [ ] On any other failure (network error, missing JSON field, etc.), the message `'Evocazione fallita per cause misteriose. Riprova.'` is sent
- [ ] The handler never silently drops an error; the `.otherwise()` callback always sends a message
- [ ] `KoboldoHandler::__invoke` performs no blocking I/O and returns `void`
- [ ] `KaasClient` is injected via the constructor (not instantiated inside the handler)

**Story Points:** 3
**Priority:** Critical
**Dependencies:** 008-kobold-data-value-object, 009-kaas-client-service
**Notes:** File to modify: `app/Handlers/KoboldoHandler.php` (replace the stub). The handler receives `KaasClient` via constructor: `public function __construct(private readonly KaasClient $kaas) {}`. API values are embedded verbatim — no sanitisation. The `formatSheet` private method builds the markdown string using a heredoc. The `errorMessage` private method uses `match(true)` with `str_starts_with` checks on the exception message. Full implementation provided in the spec.
