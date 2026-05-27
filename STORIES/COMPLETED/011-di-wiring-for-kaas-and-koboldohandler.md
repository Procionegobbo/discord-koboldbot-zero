## Wire KaasClient and KoboldoHandler into the Service Container

**As a** developer
**I want** `KaasClient` bound as a singleton and `KoboldoHandler` resolved via the Laravel container
**So that** dependency injection is container-managed and no manual instantiation is needed in production

**Acceptance Criteria:**
- [ ] `app/Providers/AppServiceProvider.php` registers `KaasClient::class` as a singleton that wraps `new Browser`
- [ ] `KoboldoHandler` is resolved via `$app->make(KoboldoHandler::class)` in the registry closure, so the container injects `KaasClient` automatically
- [ ] The bot starts without errors and the `!koboldo` command is dispatched to the new `KoboldoHandler`
- [ ] No manual `new KoboldoHandler(new KaasClient(new Browser))` call appears in application code

**Story Points:** 2
**Priority:** Critical
**Dependencies:** 009-kaas-client-service, 010-koboldohandler-implementation
**Notes:** File to modify: `app/Providers/AppServiceProvider.php`. Add to `register()`:
```php
use App\Services\KaasClient;
use React\Http\Browser;

$this->app->singleton(KaasClient::class, function () {
    return new KaasClient(new Browser);
});
```
Update the `!koboldo` registration in the registry closure to use `$app->make(KoboldoHandler::class)` instead of `new KoboldoHandler()`. Constructor auto-wiring handles `KaasClient` injection once the singleton binding exists.
