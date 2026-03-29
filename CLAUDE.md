# coinbase-commerce-php

PHP client library for two Coinbase APIs:

| API | Auth | Resources |
|-----|------|-----------|
| Commerce (legacy) | `X-CC-Api-Key` header | `Charge`, `Checkout`, `Invoice`, `Event` |
| Payment Link (new) | JWT Bearer ES256 | `PaymentLink` |

## Commands

```bash
./vendor/bin/phpunit --verbose    # run all tests
./vendor/bin/phpunit --coverage-text  # coverage report
./vendor/bin/phpcs                # lint (phpcs.xml rules)
composer install                  # install deps
```

## Architecture

**CI/Deployment:** `.circleci/config.yml` defines CircleCI pipelines for automated testing and deploy · `.github/` holds `ISSUE_REQUEST_TEMPLATE`, `PULL_REQUEST_TEMPLATE`, and `dependabot.yml` for dependency updates

**Namespaces:** `CoinbaseCommerce\` → `src/` · `CoinbaseCommerce\Tests\` → `tests/`

**Commerce API** (`src/`):
- `ApiClient` — singleton, `ApiClient::init($apiKey)`, Guzzle-backed, PSR-3 logger via `setLogger()`
- `ApiResource` (`src/Resources/ApiResource.php`) — base class, extends `\ArrayObject`, dirty-tracking via `getDirtyAttributes()`
- Resources (`src/Resources/`): `Charge` · `Checkout` · `Event` · `Invoice` — each implements `ResourcePathInterface` and composes operation traits
- Operation traits (`src/Resources/Operations/`): `CreateMethodTrait` · `ReadMethodTrait` · `UpdateMethodTrait` · `DeleteMethodTrait` · `SaveMethodTrait`
- `ApiResourceList` (`src/ApiResourceList.php`) — paginated list with `hasNext()` / `loadNext()` / `hasPrev()` / `loadPrev()`
- `ApiErrorFactory` (`src/ApiErrorFactory.php`) — maps HTTP codes + error type strings to typed exceptions
- `Webhook` (`src/Webhook.php`) — HMAC-SHA256 signature verification, returns `Event` object

**Payment Link API** (`src/PaymentLink/`):
- `PaymentLinkClient` — JWT ES256 via `firebase/php-jwt`, `generateJwt($method, $path)`, `get()` / `post()`
- `PaymentLink` — static facade: `create()` / `get()` / `list()` / `deactivate()`, requires `PaymentLink::setClient($client)` first
- `PaymentLinkWebhook` — HMAC-SHA256 with timestamp + header-name signing: `buildEvent($payload, $sigHeader, $secret, $headers)`

**Exceptions** (`src/Exceptions/`): `CoinbaseException` → `ApiException` → typed subclasses (`AuthenticationException`, `InvalidRequestException`, `ResourceNotFoundException`, `RateLimitExceededException`, `InternalServerException`, `ServiceUnavailableException`, `ValidationException`, `ParamRequiredException`) · `SignatureVerificationException` · `InvalidResponseException`

**Util** (`src/Util.php`): `convertToApiObject()` maps `resource` field to class · `joinPath()` · `hashEqual()`

## Coding Conventions

- New resource class: extend `ApiResource`, implement `ResourcePathInterface`, use operation traits
- `getResourcePath()` returns the API path string (e.g. `'charges'`)
- `save()` dispatches to `insert()` (no id) or `update()` (has id) — requires `UpdateMethodTrait` for update path
- `Util::convertToApiObject()` auto-promotes `['resource' => 'charge']` responses to `Charge` objects — add mapping in `src/Util.php` for new resource types
- API responses: `ApiResponse->bodyArray['data']` for single · `bodyArray['data']` array + `bodyArray['pagination']` for lists
- Never instantiate `ApiClient` directly — use `ApiClient::init()` / `ApiClient::getInstance()`
- PaymentLink API errors use `errorType` / `errorMessage` / `correlationId` keys (not `error.type`)
- PSR-2 coding standard enforced by `phpcs.xml`

## Testing

- All resource tests extend `tests/BaseTest.php` — provides `appendRequest($code, $body)`, `assertRequested($method, $path, $params)`, `parseJsonFile($name)`
- `GuzzleMockClientFactoryMethod::create()` auto-selects Guzzle 5 vs 6/7 mock helper
- Fixtures in `tests/Fixtures/`: `charge.json` · `chargeList.json` · `checkout.json` · `checkoutList.json` · `event.json` · `eventList.json` · `firstPageChargeList.json` · `secondPageChargeList.json`
- PaymentLink tests in `tests/PaymentLink/` use `createMock(PaymentLinkClient::class)` directly
- EC key generation in `tests/PaymentLink/PaymentLinkClientTest.php` uses `openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])`

## Examples

Commerce API initialization and webhook verification:

```php
ApiClient::init($apiKey);
$charge = Charge::create(['name' => 'Widget', 'pricing_type' => 'fixed_price', 'local_price' => ['amount' => '10.00', 'currency' => 'USD']]);
$event = Webhook::buildEvent($rawBody, $sigHeader, $webhookSecret);
```

Payment Link API:

```php
$client = new PaymentLinkClient($keyName, $privateKeyPem);
PaymentLink::setClient($client);
$link = PaymentLink::create(['name' => 'My Product', 'description' => 'A product']);
$event = PaymentLinkWebhook::buildEvent($payload, $sigHeader, $secret, $headers);
```

- Commerce resources: `examples/Resources/` — `ChargeExample.php` · `CheckoutExample.php` · `EventExample.php` · `InvoiceExample.php`
- Commerce webhook: `examples/Webhook/Webhook.php` — uses `Webhook::buildEvent($payload, $sigHeader, $secret)`
- Payment Link: `examples/PaymentLink/PaymentLinkExample.php` · `examples/PaymentLink/WebhookExample.php`
- Each examples dir has its own `composer.json` requiring `detain/coinbase-commerce`

<!-- caliber:managed:pre-commit -->
## Before Committing

Run `caliber refresh` before creating git commits to keep docs in sync with code changes.
After it completes, stage any modified doc files before committing:

```bash
caliber refresh && git add CLAUDE.md .claude/ .cursor/ .github/copilot-instructions.md AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null
```
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
