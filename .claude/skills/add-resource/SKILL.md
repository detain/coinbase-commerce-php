---
name: add-resource
description: Creates a new Commerce API resource class in src/Resources/ following the trait composition pattern used by Charge, Checkout, Invoice, and Event. Generates the class skeleton, wires correct operation traits (CreateMethodTrait, ReadMethodTrait, UpdateMethodTrait, DeleteMethodTrait, SaveMethodTrait), implements ResourcePathInterface, and registers the resource type in src/Util.php convertToApiObject(). Use when user says 'add resource', 'new resource type', 'create API resource', or adds files to src/Resources/. Do NOT use for PaymentLink-namespace resources (those live in src/PaymentLink/).
---
# add-resource

## Critical

- **Never** place Commerce resources under `src/PaymentLink/` — that namespace is for the JWT-auth Payment Link API only.
- `SaveMethodTrait` requires `UpdateMethodTrait` to be present if the resource supports updates. If the resource is read-only or create-only (like `Event`), omit `SaveMethodTrait`.
- `getResourcePath()` **must** be `static` and return the plural lowercase path string (e.g. `'charges'`).
- After creating the class you **must** register it in `src/Util.php` or API responses will never auto-promote to the new type.

## Instructions

1. **Decide which operation traits the resource needs** based on what the API supports:
   - Read-only → `ReadMethodTrait`
   - Create + read → `CreateMethodTrait, ReadMethodTrait, SaveMethodTrait`
   - Full CRUD → `ReadMethodTrait, CreateMethodTrait, UpdateMethodTrait, DeleteMethodTrait, SaveMethodTrait`
   - Custom sub-actions (resolve, cancel, void) require a manual method — see Step 3.
   
   Verify the API actually exposes each verb before including the corresponding trait.

2. **Create the resource class file** (e.g., `src/Resources/Refund.php`) using this exact skeleton (adjust class name, traits, and path):

   ```php
   <?php
   namespace CoinbaseCommerce\Resources;
   
   use CoinbaseCommerce\Resources\Operations\CreateMethodTrait;
   use CoinbaseCommerce\Resources\Operations\DeleteMethodTrait;
   use CoinbaseCommerce\Resources\Operations\ReadMethodTrait;
   use CoinbaseCommerce\Resources\Operations\SaveMethodTrait;
   use CoinbaseCommerce\Resources\Operations\UpdateMethodTrait;
   
   class Widget extends ApiResource implements ResourcePathInterface
   {
       use ReadMethodTrait, CreateMethodTrait, UpdateMethodTrait, DeleteMethodTrait, SaveMethodTrait;
   
       /**
        * @return string
        */
       public static function getResourcePath()
       {
           return 'widgets';
       }
   }
   ```

   Only import the `use` lines for traits you actually include. Verify the new file was created before proceeding.

3. **(Optional) Add custom sub-action methods** — model after `Charge::resolve()`:

   ```php
   use CoinbaseCommerce\Util;
   
   public function void($headers = [])
   {
       $id = $this->id;
       $path = Util::joinPath(static::getResourcePath(), $id, 'void');
       $client = static::getClient();
       $response = $client->post($path, [], $headers);
       $this->refreshFrom($response);
   }
   ```

   Add `use CoinbaseCommerce\Util;` to the import block when using `Util::joinPath()`.

4. **Register the resource type in `src/Util.php`** inside `getResourceClassByName()`:

   a. Add a `use` import at the top of `Util.php`:
      ```php
      use CoinbaseCommerce\Resources\Widget;
      ```
   b. Add the lowercase resource-type key (this must match the `resource` field in API JSON responses) to `$mapResourceByName`:
      ```php
      'widget' => Widget::getClassName(),
      ```

   Verify `self::$mapResourceByName` now contains the new key before running tests.

5. **Run the test suite** to confirm nothing is broken:
   ```bash
   ./vendor/bin/phpunit --verbose
   ```

## Examples

**User says:** "Add a Refund resource that supports read and list only."

**Actions:**

1. Traits needed: `ReadMethodTrait` only (no create/update/delete).
2. Create `src/Resources/Refund.php`:
   ```php
   <?php
   namespace CoinbaseCommerce\Resources;
   
   use CoinbaseCommerce\Resources\Operations\ReadMethodTrait;
   
   class Refund extends ApiResource implements ResourcePathInterface
   {
       use ReadMethodTrait;
   
       /**
        * @return string
        */
       public static function getResourcePath()
       {
           return 'refunds';
       }
   }
   ```
3. In `src/Util.php`, add `use CoinbaseCommerce\Resources\Refund;` and `'refund' => Refund::getClassName()` to `$mapResourceByName`.
4. Run `./vendor/bin/phpunit --verbose` — all tests pass.

**Result:** `Refund::retrieve($id)`, `Refund::getList()`, and `Refund::getAll()` are available. API responses with `"resource": "refund"` auto-promote to `Refund` objects.

## Common Issues

- **`Call to undefined method ... getClassName()`** — `ApiResource` provides `getClassName()` via a static helper; confirm your class extends `ApiResource`, not a plain class.
- **API response not auto-promoted to object (still plain array)** — the `resource` key in `$mapResourceByName` must exactly match the string in the JSON `"resource"` field. Check the raw API response and compare.
- **`Cannot use SaveMethodTrait ... because the name ... is already in use`** — `SaveMethodTrait` calls `update()` which is defined in `UpdateMethodTrait`. Include `UpdateMethodTrait` alongside `SaveMethodTrait`, or omit `SaveMethodTrait` for read/create-only resources.
- **Tests fail with `Class 'CoinbaseCommerce\Resources\Widget' not found`** — run `composer dump-autoload` to regenerate the PSR-4 classmap after adding the new file.
- **Custom sub-action sends wrong path** — `Util::joinPath()` trims leading/trailing slashes from each segment and joins with `/`. Do not pass a leading `/` in the action name: use `'void'`, not `'/void'`.
