## Add KaaS URL to Discord Config

**As a** bot operator
**I want** the KaaS endpoint URL defined in `config/discord.php` with an overridable default
**So that** the URL can be changed per environment (e.g., CI mock server) without code changes

**Acceptance Criteria:**
- [ ] `config/discord.php` contains the key `'kaas_url'` with value `env('KAAS_URL', 'https://kaas.procionegobbo.it/api/generate-kobold')`
- [ ] The default value resolves to `'https://kaas.procionegobbo.it/api/generate-kobold'` when `KAAS_URL` is not set in the environment
- [ ] Setting `KAAS_URL` in the environment overrides the default value
- [ ] No other files are changed as part of this story

**Story Points:** 1
**Priority:** High
**Dependencies:** None
**Notes:** Add the new key to the existing `config/discord.php` array, following the same `env()` pattern used by other keys in that file. Consumers must read the URL via `config('discord.kaas_url')`, never from a hardcoded class constant.
