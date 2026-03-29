---
name: exception-mapping
description: Adds a new typed exception class in src/Exceptions/ and wires it into ApiErrorFactory.php (Commerce API) or the $errorTypeMap in src/PaymentLink/PaymentLinkClient.php (PaymentLink API). Use when user says 'add exception', 'new error type', 'handle error code', or when a new HTTP status or API error string needs a dedicated class. Do NOT use for webhook signature errors (use SignatureVerificationException) or invalid response body errors (use InvalidResponseException) — those extend CoinbaseException directly.
---
# exception-mapping

## Critical

- **Extend `ApiException`** for all HTTP-triggered errors (have a request/response context). **Extend `CoinbaseException` directly** only for non-HTTP errors like signature or response-parsing failures.
- `ApiException`'s constructor signature is `__construct($message, $request, $response, $previous)` — typed subclasses must NOT define their own constructor unless adding custom fields.
- After creating the class file, you **must** wire the new exception into both lookup maps where applicable: `ApiErrorFactory::$mapErrorMessageToClass` (error type string) and/or `ApiErrorFactory::$mapErrorCodeToClass` (HTTP status), and `PaymentLinkClient::$errorTypeMap` if the error type applies to the Payment Link API.
- Run `./vendor/bin/phpunit --verbose` before finishing — do not skip test validation.

## Instructions

1. **Create the exception class file** under `src/Exceptions/` (e.g., `src/Exceptions/ConflictException.php`):
   ```php
   <?php
   namespace CoinbaseCommerce\Exceptions;

   class YourNewException extends ApiException
   {
   }
   ```
   For non-HTTP errors (no request/response), extend `CoinbaseException` instead and add a custom constructor if needed (see `SignatureVerificationException` pattern).
   Verify the new file was created before proceeding.

2. **Wire into `src/ApiErrorFactory.php`** (Commerce API). Add a `use` import at the top, then add the mapping in the appropriate static array:
   - If triggered by an API error type string (e.g. `'not_found'`), add to `$mapErrorMessageToClass`:
     ```php
     'your_error_type' => YourNewException::getClassName(),
     ```
   - If triggered by HTTP status code, add to `$mapErrorCodeToClass`:
     ```php
     422 => YourNewException::getClassName(),
     ```
   - Add the `use` statement with all others at the top of `ApiErrorFactory.php`:
     ```php
     use CoinbaseCommerce\Exceptions\YourNewException;
     ```
   Verify both the `use` line and the map entry are present.

3. **Wire into `src/PaymentLink/PaymentLinkClient.php`** (Payment Link API), if the error type also applies there. Add a `use` import and an entry in `$errorTypeMap`:
   ```php
   use CoinbaseCommerce\Exceptions\YourNewException;
   // ...
   private static array $errorTypeMap = [
       // existing entries...
       'your_error_type' => YourNewException::class,  // NOTE: ::class not ::getClassName()
   ];
   ```
   Note: `PaymentLinkClient` uses `ClassName::class`; `ApiErrorFactory` uses `ClassName::getClassName()`.

4. **Add a test fixture** in `tests/ExceptionsTest.php` inside `getFixtures()`:
   ```php
   [
       'response' => [
           'statusCode' => 422,
           'body' => [
               'error' => [
                   'type' => 'your_error_type',
                   'message' => 'Descriptive error message'
               ]
           ]
       ],
       'exceptionClass' => YourNewException::getClassName()
   ],
   ```
   Add the `use CoinbaseCommerce\Exceptions\YourNewException;` import at the top of `ExceptionsTest.php`.

5. **Run tests** to confirm:
   ```bash
   ./vendor/bin/phpunit --verbose
   ```
   All existing tests must still pass and the new fixture must be exercised.

## Examples

**User says:** "Add a `ConflictException` for HTTP 409 conflict errors with error type `conflict_error`"

**Actions taken:**
1. Create `src/Exceptions/ConflictException.php`:
   ```php
   <?php
   namespace CoinbaseCommerce\Exceptions;

   class ConflictException extends ApiException
   {
   }
   ```
2. In `src/ApiErrorFactory.php`, add `use CoinbaseCommerce\Exceptions\ConflictException;` and:
   - `'conflict_error' => ConflictException::getClassName(),` in `$mapErrorMessageToClass`
   - `409 => ConflictException::getClassName(),` in `$mapErrorCodeToClass`
3. In `src/PaymentLink/PaymentLinkClient.php`, add `use` and `'conflict_error' => ConflictException::class,` in `$errorTypeMap`.
4. In `tests/ExceptionsTest.php`, add fixture for `statusCode: 409` / `type: 'conflict_error'` → `ConflictException::getClassName()`.

**Result:** `./vendor/bin/phpunit --verbose` passes; a 409 response with `"type": "conflict_error"` now throws `ConflictException`.

## Common Issues

- **`Call to undefined method getClassName()`**: You used `::class` in `ApiErrorFactory.php` instead of `::getClassName()`. Fix: change `ConflictException::class` → `ConflictException::getClassName()` in `ApiErrorFactory.php` only. `PaymentLinkClient.php` correctly uses `::class`.
- **New exception never thrown, falls back to `ApiException`**: The error type string key in the map doesn't match what the API returns. Check the raw response body — the key is `error.type` for Commerce and `errorType` for Payment Link. Ensure your map key exactly matches.
- **`Class 'CoinbaseCommerce\Exceptions\YourNewException' not found`**: Missing `use` import in `ApiErrorFactory.php` or `PaymentLinkClient.php`. Both files require explicit `use` statements.
- **Test fixture not exercised / no assertion runs**: The `catch (\Exception $exception)` block in `testApiExceptions` is only reached if the request throws. Ensure `appendRequest()` is called with the right status code and the fixture body matches the `error.type` key checked in `ApiErrorFactory::create()`.
