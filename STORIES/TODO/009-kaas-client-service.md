## Implement KaasClient HTTP Service

**As a** developer
**I want** a dedicated service class that wraps `React\Http\Browser` and calls the KaaS API asynchronously
**So that** the HTTP concern is isolated from the Discord handler and can be tested independently

**Acceptance Criteria:**
- [ ] `app/Services/KaasClient.php` exists with namespace `App\Services`
- [ ] `KaasClient` accepts a `React\Http\Browser` instance via constructor injection
- [ ] `KaasClient::generate(): PromiseInterface` sends a POST request to `config('discord.kaas_url')` with header `Content-Type: application/json` and body `{"language":"it"}`
- [ ] On HTTP 200–299, the promise resolves with the raw response body as a `string`
- [ ] On HTTP 429, the promise rejects with `new \RuntimeException('rate_limit')`
- [ ] On any other non-2xx status, the promise rejects with `new \RuntimeException("http_error:{$status}")`
- [ ] Network-level errors (rejected promise from `Browser::post`) propagate unchanged
- [ ] No blocking I/O occurs; the method returns a `PromiseInterface` immediately

**Story Points:** 2
**Priority:** Critical
**Dependencies:** 007-kaas-url-config
**Notes:** Uses `React\Http\Browser::post()` which is already available as a transitive dependency of `team-reflex/discord-php` — no `composer require` needed. Exact implementation from the spec:
```php
namespace App\Services;

use React\Http\Browser;
use React\Promise\PromiseInterface;

class KaasClient
{
    public function __construct(private readonly Browser $browser) {}

    public function generate(): PromiseInterface
    {
        return $this->browser->post(
            config('discord.kaas_url'),
            ['Content-Type' => 'application/json'],
            json_encode(['language' => 'it']),
        )->then(function ($response) {
            $status = $response->getStatusCode();
            if ($status === 429) { throw new \RuntimeException('rate_limit'); }
            if ($status < 200 || $status >= 300) { throw new \RuntimeException("http_error:{$status}"); }
            return (string) $response->getBody();
        });
    }
}
```
