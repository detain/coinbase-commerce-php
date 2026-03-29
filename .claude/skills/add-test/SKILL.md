---
name: add-test
description: Writes PHPUnit tests for a Commerce API resource or utility following the BaseTest + GuzzleMockClientFactoryMethod pattern. Uses appendRequest(), assertRequested(), and parseJsonFile() from tests/BaseTest.php. Generates fixture JSON in tests/Fixtures/ and the test class under tests/Resources/. Use when user says 'write test', 'add test for', or 'test coverage'. Do NOT use for PaymentLink tests (tests/PaymentLink/) which use direct createMock() without BaseTest.
---
# add-test

## Critical

- **Never** extend `TestCase` directly for resource tests — always extend `BaseTest`.
- Call `ResourceName::setClient($this->apiClient)` in `setUp()` before any test method runs.
- `appendRequest()` must be called **before** the action that triggers the HTTP call — one call per expected request.
- `assertRequested()` consumes the request via `shiftTransactionRequest()`; call it once per HTTP interaction in order.
- Fixture files must wrap the resource object in `{"data": {...}}` at the top level (see `tests/Fixtures/charge.json`).
- List fixtures must wrap in `{"data": [...], "pagination": {...}}`.

## Instructions

1. **Create the fixture JSON** under `tests/Fixtures/` (e.g., `tests/Fixtures/invoice.json`).
   Mirror the structure of `tests/Fixtures/charge.json`: top-level `"data"` key, include `"resource": "<resourcename>"`, `"id"`, and the fields your assertions will check.
   ```json
   {
     "data": {
       "id": "488fcbd5-eb82-42dc-8a2b-10fdf70e0bfe",
       "resource": "invoice",
       "code": "ABC123",
       "name": "Test Invoice"
     }
   }
   ```
   If list tests are needed, also create a list fixture (e.g., `tests/Fixtures/invoiceList.json`) with `"data": [...]` and a `"pagination"` block.
   Verify the file exists before proceeding.

2. **Create the test class** at `tests/Resources/<Resource>Test.php`.
   - Namespace: `CoinbaseCommerce\Tests\Resources`
   - Imports: `use CoinbaseCommerce\ApiResourceList;`, `use CoinbaseCommerce\Resources\<Resource>;`, `use CoinbaseCommerce\Tests\BaseTest;`
   - Class extends `BaseTest`
   - `setUp()` calls `parent::setUp()` then `<Resource>::setClient($this->apiClient);`

   ```php
   <?php
   namespace CoinbaseCommerce\Tests\Resources;

   use CoinbaseCommerce\ApiResourceList;
   use CoinbaseCommerce\Resources\Invoice;
   use CoinbaseCommerce\Tests\BaseTest;

   class InvoiceTest extends BaseTest
   {
       public function setUp(): void
       {
           parent::setUp();
           Invoice::setClient($this->apiClient);
       }
   }
   ```

3. **Add test methods** for each operation the resource supports.
   Pattern for each test:
   ```php
   public function testCreateMethod()
   {
       $this->appendRequest(200, $this->parseJsonFile('invoice.json'));
       $obj = Invoice::create(['name' => 'Test Invoice']);

       $this->assertRequested('POST', '/invoices', '');
       $this->assertInstanceOf(Invoice::getClassName(), $obj);
       $this->assertEquals('ABC123', $obj->code);
   }
   ```
   - `assertRequested($method, $path, $queryString)` — query string is URL-encoded (e.g. `'limit=2'`) or `''` for no params.
   - For list tests use `assertInstanceOf(ApiResourceList::getClassName(), $list)`.
   - For exception tests use `$this->expectException(\Exception::class)` and `$this->expectExceptionMessage('...')` **before** `appendRequest()`.

4. **Verify test discovery** by running:
   ```bash
   ./vendor/bin/phpunit --verbose tests/Resources/
   ```
   All tests must pass with no warnings.

## Examples

**User says:** "Add tests for Invoice resource (create, retrieve, list)"

**Actions taken:**
1. Write `tests/Fixtures/invoice.json` with `{"data": {"id": "abc-123", "resource": "invoice", "code": "INV001", "name": "Test"}}`
2. Write `tests/Fixtures/invoiceList.json` with `{"data": [{...}], "pagination": {"order": "desc", "starting_after": null, "ending_before": null, "total": 1, "yielded": 1, "limit": 25, "previous_uri": null, "next_uri": null, "cursor_range": ["abc-123", "abc-123"]}}`
3. Create the test class in `tests/Resources/`:

```php
<?php
namespace CoinbaseCommerce\Tests\Resources;

use CoinbaseCommerce\ApiResourceList;
use CoinbaseCommerce\Resources\Invoice;
use CoinbaseCommerce\Tests\BaseTest;

class InvoiceTest extends BaseTest
{
    public function setUp(): void
    {
        parent::setUp();
        Invoice::setClient($this->apiClient);
    }

    public function testCreateMethod()
    {
        $this->appendRequest(200, $this->parseJsonFile('invoice.json'));
        $obj = Invoice::create(['name' => 'Test']);

        $this->assertRequested('POST', '/invoices', '');
        $this->assertInstanceOf(Invoice::getClassName(), $obj);
        $this->assertEquals('INV001', $obj->code);
    }

    public function testRetrieveMethod()
    {
        $this->appendRequest(200, $this->parseJsonFile('invoice.json'));
        $id = 'abc-123';
        $obj = Invoice::retrieve($id);

        $this->assertRequested('GET', '/invoices/' . $id, '');
        $this->assertInstanceOf(Invoice::getClassName(), $obj);
        $this->assertEquals('INV001', $obj->code);
    }

    public function testListMethod()
    {
        $this->appendRequest(200, $this->parseJsonFile('invoiceList.json'));
        $list = Invoice::getList(['limit' => 2]);

        $this->assertRequested('GET', '/invoices', 'limit=2');
        $this->assertInstanceOf(ApiResourceList::getClassName(), $list);
    }
}
```

4. Run `./vendor/bin/phpunit --verbose tests/Resources/` — all 3 tests pass.

## Common Issues

- **`File not exists` from `parseJsonFile()`**: The fixture file is missing or misnamed. Check that `tests/Fixtures/<name>.json` exactly matches the string passed to `parseJsonFile()`.
- **`Call to undefined method ... setClient()`**: The resource class is missing `use \CoinbaseCommerce\ApiRequestor;` or the client static property. Check the resource extends `ApiResource` and includes a `setClient()` trait/method like `Charge` does.
- **Assertion `assertRequested` fails with wrong path**: `appendRequest()` was not called before the action, or the resource's `getResourcePath()` returns a different string. Grep: `grep -r 'getResourcePath' src/Resources/`.
- **Test passes but no HTTP call was made**: You called `appendRequest()` but the method under test uses a different client instance. Ensure `<Resource>::setClient($this->apiClient)` is in `setUp()`.
- **`shiftTransactionRequest` returns null**: More `assertRequested()` calls than `appendRequest()` calls. Each HTTP interaction needs exactly one `appendRequest()`.
