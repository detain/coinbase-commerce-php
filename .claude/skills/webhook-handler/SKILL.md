---
name: webhook-handler
description: Implements webhook signature verification for Coinbase Commerce (legacy) or Payment Link (new) APIs. Use when user says 'webhook', 'verify signature', 'payment event handler', or references X-CC-Webhook-Signature / X-Hook0-Signature headers. Do NOT use for general HTTP request handling or non-webhook Coinbase API calls.
---
# webhook-handler

## Critical

- **Never read the payload before verifying the signature** — `PaymentLinkWebhook::buildEvent()` verifies first, then parses JSON. Do not reverse this order.
- Use the **raw request body** (not parsed/re-encoded) as `$payload` — any whitespace change breaks HMAC.
- Both classes throw `SignatureVerificationException` or `InvalidResponseException` from `src/Exceptions/`. Always catch both.
- Commerce API returns a `CoinbaseCommerce\Resources\Event` object. Payment Link API returns a plain `array`.

## Instructions

### Commerce API (legacy) — `X-CC-Webhook-Signature` header

1. Read the raw POST body and the signature header:
   ```php
   $payload   = file_get_contents('php://input');
   $sigHeader = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';
   $secret    = 'your-shared-secret'; // from Coinbase Commerce dashboard
   ```
   Verify `$payload` is non-empty and `$sigHeader` is non-empty before calling `buildEvent()`.

2. Call `Webhook::buildEvent()` — it validates JSON, checks for `event` key, then verifies HMAC-SHA256:
   ```php
   use CoinbaseCommerce\Webhook;
   use CoinbaseCommerce\Exceptions\SignatureVerificationException;
   use CoinbaseCommerce\Exceptions\InvalidResponseException;

   try {
       $event = Webhook::buildEvent($payload, $sigHeader, $secret);
   } catch (SignatureVerificationException $e) {
       http_response_code(400);
       exit('Bad signature');
   } catch (InvalidResponseException $e) {
       http_response_code(400);
       exit('Invalid payload');
   }
   ```

3. Use the returned `Event` object:
   ```php
   // $event is CoinbaseCommerce\Resources\Event (an ArrayObject)
   if ($event->type === 'charge:confirmed') {
       // handle confirmed charge — $event->data contains charge fields
   }
   ```
   Verify `$event->type` matches a known event type before acting.

### Payment Link API (new) — timestamp+header HMAC

1. Collect the raw body, the full `$_SERVER` headers array, and the signature header:
   ```php
   $payload     = file_get_contents('php://input');
   $sigHeader   = $_SERVER['HTTP_X_HOOK0_SIGNATURE'] ?? '';
   $secret      = 'your-payment-link-webhook-secret';
   // Normalize server vars to lowercase header names
   $headers = ['content-type' => $_SERVER['CONTENT_TYPE'] ?? ''];
   ```
   Pass all signed headers — the `h=` field in `$sigHeader` lists which header names were included.

2. Call `PaymentLinkWebhook::buildEvent()` with optional `$maxAgeMinutes` (default 5):
   ```php
   use CoinbaseCommerce\PaymentLink\PaymentLinkWebhook;
   use CoinbaseCommerce\Exceptions\SignatureVerificationException;
   use CoinbaseCommerce\Exceptions\InvalidResponseException;

   try {
       $event = PaymentLinkWebhook::buildEvent($payload, $sigHeader, $secret, $headers);
   } catch (SignatureVerificationException $e) {
       http_response_code(400);
       exit($e->getMessage()); // surfaces: 'maximum age', 'future', 'Malformed...'
   } catch (InvalidResponseException $e) {
       http_response_code(400);
       exit('Invalid payload');
   }
   ```

3. Use the returned array:
   ```php
   // $event is a plain PHP array
   if ($event['eventType'] === 'payment_link.payment.success') {
       $amount = $event['amount'];
       $status = $event['status']; // 'COMPLETED'
   }
   ```
   Verify `$event['eventType']` before acting.

## Examples

**Commerce webhook endpoint:**
```
User: add a webhook handler for charge:confirmed events
```
Actions:
- Read raw body via `file_get_contents('php://input')`
- Extract `$_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE']`
- Call `Webhook::buildEvent($payload, $sigHeader, $secret)` → returns `Event`
- Branch on `$event->type === 'charge:confirmed'`

**Payment Link webhook endpoint:**
```
User: handle payment_link.payment.success webhooks
```
Actions:
- Read raw body + `HTTP_X_HOOK0_SIGNATURE` + `CONTENT_TYPE` header
- Call `PaymentLinkWebhook::buildEvent($payload, $sigHeader, $secret, ['content-type' => ...])` → returns `array`
- Branch on `$event['eventType'] === 'payment_link.payment.success'`

## Common Issues

- **`SignatureVerificationException` on valid requests** — you re-encoded the body (e.g., `json_encode(json_decode($body))`). Pass the raw string from `php://input` unchanged.
- **`Malformed webhook signature header: missing required fields (t, h, v1)`** — Payment Link header format is `t=TIMESTAMP,h=header-name,v1=HEXSIG`. Check that you're reading the correct header (`X-Hook0-Signature`), not `X-CC-Webhook-Signature`.
- **`Webhook timestamp exceeds maximum age`** — clock skew or delayed processing. Default window is 5 minutes. Pass a larger `$maxAgeMinutes` to `buildEvent()` only in development; do not widen in production.
- **`Invalid payload provided. No JSON object could be decoded`** — Commerce API only: payload must contain a top-level `event` key. Test with the fixture at `tests/Fixtures/event.json`.
- **Header lookup fails silently in Payment Link** — headers are normalized to lowercase internally. Pass either `['content-type' => ...]` or `['Content-Type' => ...]`; both work. But the `h=` field in the signature header must match the names you signed.
- **Run tests:** `./vendor/bin/phpunit tests/WebhookTest.php tests/PaymentLink/PaymentLinkWebhookTest.php --verbose`