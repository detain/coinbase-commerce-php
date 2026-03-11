[![CircleCI](https://circleci.com/gh/detain/coinbase-commerce-php/tree/master.svg?style=svg)](https://circleci.com/gh/detain/coinbase-commerce-php/tree/master)
# Coinbase Commerce

PHP library for the [Coinbase Commerce API](https://commerce.coinbase.com/docs/) and the new [Coinbase Payment Link API](https://docs.cdp.coinbase.com/commerce-onchain/docs/payment-links).

# Table of contents

<!--ts-->
   * [PHP Versions](#php-versions)
   * [Documentation](#documentation)
   * [Installation](#installation)
   * [Usage](#usage)
      * [Commerce API (Legacy)](#commerce-api-legacy)
         * [Checkouts](#checkouts)
         * [Charges](#charges)
         * [Invoices](#invoices)
         * [Events](#events)
         * [Webhooks](#webhooks)
         * [Warnings](#warnings)
      * [Payment Link API (New)](#payment-link-api-new)
         * [Authentication](#authentication)
         * [Create a Payment Link](#create-a-payment-link)
         * [Get a Payment Link](#get-a-payment-link)
         * [List Payment Links](#list-payment-links)
         * [Deactivate a Payment Link](#deactivate-a-payment-link)
         * [Idempotency](#idempotency)
         * [Payment Link Webhooks](#payment-link-webhooks)
   * [Testing and Contributing](#testing-and-contributing)
<!--te-->

## PHP versions
PHP version 7.4 and above is supported.

## Documentation

This library supports two Coinbase APIs:

| | Commerce API (Legacy) | Payment Link API (New) |
|---|---|---|
| **Auth** | `X-CC-Api-Key` header | JWT Bearer token (ES256) |
| **Base URL** | `api.commerce.coinbase.com` | `business.coinbase.com/api/v1` |
| **Currency** | Multiple cryptocurrencies | USDC only |
| **Resources** | Checkout, Charge, Invoice, Event | PaymentLink |
| **Namespace** | `CoinbaseCommerce\` | `CoinbaseCommerce\PaymentLink\` |

- **Commerce API docs:** [commerce.coinbase.com/docs/api](https://commerce.coinbase.com/docs/api/)
- **Payment Link API docs:** [docs.cdp.coinbase.com/commerce-onchain/docs/payment-links](https://docs.cdp.coinbase.com/commerce-onchain/docs/payment-links)

All errors that occur during any interaction with either API will be raised as exceptions:

| Error                        | Status Code |
|------------------------------|-------------|
| APIException                 |      *      |
| InvalidRequestException      |     400     |
| ParamRequiredException       |     400     |
| ValidationException          |     400     |
| AuthenticationException      |     401     |
| ResourceNotFoundException    |     404     |
| RateLimitExceededException   |     429     |
| InternalServerException      |     500     |
| ServiceUnavailableException  |     503     |

## Installation

Install with ``composer``:
``` sh
composer require detain/coinbase-commerce
```

This will also install `firebase/php-jwt` (required for the Payment Link API).

## Usage

---

## Commerce API (Legacy)

To start using the Commerce API, register an account on [Coinbase Commerce](https://commerce.coinbase.com/signup).
You will find your ``API_KEY`` from User Settings.

Initialize a ``Client`` for interacting with the API:
``` php
use CoinbaseCommerce\ApiClient;

//Make sure you don't store your API Key in your source code!
$apiClientObj = ApiClient::init(<API_KEY>);
$apiClientObj->setTimeout(3);
```

### Disable SSL Check
``` php
$apiClientObj->verifySsl(false);
```

The API resource class provides the following static methods: ``list, all, create, retrieve, updateById, deleteById``.  Additionally, the API resource class also provides the following instance methods: ``save, delete, insert, update``.

Each API method returns an ``ApiResource`` which represents the JSON response from the API.
When the response data is parsed into objects, the appropriate ``ApiResource`` subclass will automatically be used.

### Warnings
It's prudent to be conscious of warnings. The library will log all warnings to a standard PSR-3 logger if one is configured.
``` php
use CoinbaseCommerce\ApiClient;

//Make sure you don't store your API Key in your source code!
$apiClientObj = ApiClient::init(<API_KEY>);
$apiClientObj->setLogger($logger);
```

## Checkouts
[Checkouts API docs](https://commerce.coinbase.com/docs/api/#checkouts)
More examples on how to use checkouts can be found in the [`examples/Resources/CheckoutExample.php`](examples/Resources/CheckoutExample.php) file

### Load checkout resource class
``` php
use CoinbaseCommerce\Resources\Checkout;
```
### Retrieve
``` php
$checkoutObj = Checkout::retrieve(<checkout_id>);
```
### Create
``` php
$checkoutData = [
    'name' => 'The Sovereign Individual',
    'description' => 'Mastering the Transition to the Information Age',
    'pricing_type' => 'fixed_price',
    'local_price' => [
        'amount' => '100.00',
        'currency' => 'USD'
    ],
    'requested_info' => ['name', 'email']
];
$newCheckoutObj = Checkout::create($checkoutData);

// or

$newCheckoutObj = new Checkout();

$newCheckoutObj->name = 'The Sovereign Individual';
$newCheckoutObj->description = 'Mastering the Transition to the Information Age';
$newCheckoutObj->pricing_type = 'fixed_price';
$newCheckoutObj->local_price = [
    'amount' => '100.00',
    'currency' => 'USD'
];
checkoutObj->requested_info = ['name', 'email'];

checkoutObj->save();
```
### Update
``` php
$checkoutObj = new Checkout();

$checkoutObj->id = <checkout_id>;
$checkoutObj->name = 'new name';

$checkoutObj->save();
// or
$newParams = [
    'name' => 'New name'
];

Checkout::updateById(<checkout_id>, $newParams});
```
### Delete
``` php
$checkoutObj = new Checkout();

$checkoutObj->id = <checkout_id>;
$checkoutObj->delete();

// or

Checkout::deleteById(<checkout_id>);
```
### List
List method returns ApiResourceList object.  

``` php
$params = [
    'limit' => 2,
    'order' => 'desc'
];

$list = Checkout::getList($params);

foreach($list as $checkout) {
    var_dump($checkout);
}

// Get number of items in list
$count = $list->count();

// or
$count = count($list);

// Get number of all checkouts
$countAll = $list->countAll();

// Get pagination
$pagination = $list->getPagination();

// To load next page with previous setted params(in this case limit, order)
if ($list->hasNext()) {
    $list->loadNext();
    
    foreach($list as $checkout) {
        var_dump($checkout);
    }
}

```
### Get all checkouts
``` php
$params = [
    'order' => 'desc'  
];

$allCheckouts = Checkout::getAll($params);

```
## Charges
[Charges API docs](https://commerce.coinbase.com/docs/api/#charges)
More examples on how to use charges can be found in the [`examples/Resources/ChargeExample.php`](examples/Resources/ChargeExample.php) file

### Load charge resource class
``` php
use CoinbaseCommerce\Resources\Charge;
```
### Retrieve
``` php
$chargeObj = Charge::retrieve(<charge_id>);
```
### Create
``` php
$chargeData = [
    'name' => 'The Sovereign Individual',
    'description' => 'Mastering the Transition to the Information Age',
    'local_price' => [
        'amount' => '100.00',
        'currency' => 'USD'
    ],
    'pricing_type' => 'fixed_price'
];
Charge::create($chargeData);

// or
$chargeObj = new Charge();

$chargeObj->name = 'The Sovereign Individual';
$chargeObj->description = 'Mastering the Transition to the Information Age';
$chargeObj->local_price = [
    'amount' => '100.00',
    'currency' => 'USD'
];
$chargeObj->pricing_type = 'fixed_price';
$chargeObj->save();
```
### List
``` php
$list = Charge::getList();

foreach($list as $charge) {
    var_dump($list);
}

$pagination = $list->getPagination();
```
### Get all charges
``` php
$allCharges = Charge::getAll();
```

### Resolve a charge
Resolve a charge that has been previously marked as unresolved. 
```
$chargeObj = Charge::retrieve(<charge_id>);

if ($chargeObj) {
    $chargeObj->resolve();
}
```

### Cancel a charge
Cancels a charge that has been previously created. 
Note: Only new charges can be successfully canceled. Once payment is detected, charge can no longer be canceled.

```
$chargeObj = Charge::retrieve(<charge_id>);

if ($chargeObj) {
    $chargeObj->cancel();
}
```

## Invoices
[Invoices API docs](https://commerce.coinbase.com/docs/api/#invoices)
More examples on how to use charges can be found in the [`examples/Resources/InvoiceExample.php`](examples/Resources/InvoiceExample.php) file

### Load invoice resource class
``` php
use CoinbaseCommerce\Resources\Invoice;
```
### Retrieve
``` php
$invoiceObj = Invoice::retrieve(<invoice_id>);
```
### Create
``` php
$invoiceData = [
    'business_name' => 'Crypto Account LLC',
    'customer_email' => 'customer@test.com',
    'customer_name' => 'Test Customer',
    'local_price' => [
        'amount' => '100.00',
        'currency' => 'USD'
    ],
    'memo' => 'Taxes and Accounting Services'
];
Invoice::create($invoiceData);

// or
$invoiceObj = new Invoice();

$invoiceObj->business_name = 'Crypto Account LLC';
$invoiceObj->customer_email = 'customer@test.com';
$invoiceObj->customer_name = 'Test Customer';
$invoiceObj->local_price = [
    'amount' => '100.00',
    'currency' => 'USD'
];
$invoiceObj->memo = 'Taxes and Accounting Services';
$invoiceObj->save();
```
### List
``` php
$list = Invoice::getList();

foreach($list as $invoice) {
    var_dump($list);
}

$pagination = $list->getPagination();
```
### Get all invoices
``` php
$allInvoices = Invoice::getAll();
```

### Resolve an invoice
Resolve an invoice that has been previously marked as unresolved. 
```
$invoiceObj = Invoice::retrieve(<charge_id>);

if ($invoiceObj) {
    $invoiceObj->resolve();
}
```

### Void an invoice
Voids an invoice that has been previously created. 
Note: Only new or viewed invoices can be successfully voided. Once payment is detected, invoice can no longer be canceled.

```
$invoiceObj = Invoice::retrieve(<invoice_id>);

if ($invoiceObj) {
    $invoiceObj->void();
}
```

## Events
[Events API Docs](https://commerce.coinbase.com/docs/api/#events)
More examples on how to use events can be found in the [`examples/Resources/EventExample.php`](examples/Resources/EventExample.php) file

### Load event resource class
``` php
use CoinbaseCommerce\Resources\Event;
```
### Retrieve
``` php
$eventObj = Event::retrieve(<event_id>);
```
### List
``` php
$listEvent = Event::getList();

foreach($listEvent as $event) {
    var_dump($event);
}

$pagination = $listEvent->getPagination();
```
### Get all events
``` php
$allEvents = Event::getAll();
```

## Webhooks
Coinbase Commerce signs the webhook events it sends to your endpoint, allowing you to validate and verify that they weren't sent by someone else.
You can find a simple example of how to use this with Express in the [`examples/Webhook`](examples/Webhook) folder
### Verify Signature header
``` php
use CoinbaseCommerce\Webhook;

try {
    Webhook::verifySignature($signature, $body, $sharedSecret);
    echo 'Successfully verified';
} catch (\Exception $exception) {
    echo $exception->getMessage();
    echo 'Failed';
}
```

---

## Payment Link API (New)

The Payment Link API uses CDP (Coinbase Developer Platform) API keys with ES256 JWT authentication. It currently supports **USDC** payments only.

More examples can be found in [`examples/PaymentLink/`](examples/PaymentLink/).

### Authentication

You'll need a CDP API key from the [Coinbase Developer Platform](https://portal.cdp.coinbase.com/). This gives you a **key name** (used as the JWT `sub`/`kid`) and an **EC private key** (PEM format) for ES256 signing.

``` php
use CoinbaseCommerce\PaymentLink\PaymentLinkClient;
use CoinbaseCommerce\PaymentLink\PaymentLink;

// Initialize the client with your CDP credentials
$client = new PaymentLinkClient(
    getenv('COINBASE_CDP_KEY_NAME'),      // CDP API key ID
    getenv('COINBASE_CDP_PRIVATE_KEY')     // EC private key PEM
);

// Optional: configure timeout (default 10s)
$client = new PaymentLinkClient($keyName, $privateKey, [
    'timeout' => 15,
]);

// Set the client for the static PaymentLink facade
PaymentLink::setClient($client);
```

### Create a Payment Link
``` php
$result = PaymentLink::create([
    'amount' => '100.00',
    'currency' => 'USDC',
    'description' => 'Payment for order #12345',
    'successRedirectUrl' => 'https://example.com/success',
    'failRedirectUrl' => 'https://example.com/failed',
    'metadata' => [
        'orderId' => '12345',
        'customerId' => 'cust_abc123',
    ],
]);

echo "Payment URL: {$result['url']}\n";
echo "Status: {$result['status']}\n"; // ACTIVE
```

### Get a Payment Link
``` php
$link = PaymentLink::get($linkId);
echo "Status: {$link['status']}\n";
```

### List Payment Links
``` php
$list = PaymentLink::list([
    'pageSize' => 10,
    'status' => 'ACTIVE',
]);

foreach ($list['paymentLinks'] as $link) {
    echo "{$link['id']}: {$link['status']}\n";
}
```

### Deactivate a Payment Link
``` php
$deactivated = PaymentLink::deactivate($linkId);
echo "Status: {$deactivated['status']}\n"; // DEACTIVATED
```

### Idempotency
Pass an idempotency key to safely retry `create` requests:
``` php
$result = PaymentLink::create($params, 'unique-request-id-123');
```

### Payment Link Webhooks

The Payment Link API uses a different webhook signature format (`X-Hook0-Signature`) than the Commerce API.
See [`examples/PaymentLink/WebhookExample.php`](examples/PaymentLink/WebhookExample.php) for a complete example.

``` php
use CoinbaseCommerce\PaymentLink\PaymentLinkWebhook;

$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_HOOK0_SIGNATURE'] ?? '';

// Collect request headers (normalized to lowercase keys)
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$headerName] = $value;
    }
}
$headers['content-type'] = $_SERVER['CONTENT_TYPE'] ?? 'application/json';

try {
    // Verifies signature, checks replay protection (5 min default), then parses JSON
    $event = PaymentLinkWebhook::buildEvent($payload, $signatureHeader, $webhookSecret, $headers);

    switch ($event['eventType']) {
        case 'payment_link.payment.success':
            // Fulfill the order
            break;
        case 'payment_link.payment.failed':
            // Handle failure
            break;
        case 'payment_link.payment.expired':
            // Handle expiry
            break;
    }

    http_response_code(200);
} catch (\CoinbaseCommerce\Exceptions\SignatureVerificationException $e) {
    http_response_code(400);
}
```

#### Payment Link Statuses

| Status | Description |
|--------|-------------|
| `ACTIVE` | Link is live and accepting payments |
| `PROCESSING` | Payment is being processed |
| `COMPLETED` | Payment received successfully |
| `EXPIRED` | Link expired without payment |
| `DEACTIVATED` | Manually deactivated |
| `FAILED` | Payment failed |

---

### Testing and Contributing
Any and all contributions are welcome! The process is simple: fork this repo, make your changes, run the test suite, and submit a pull request. To run the tests, clone the repository and run the following commands:

``` sh
composer install
composer test
```

License
----

Apache-2.0
