<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use CoinbaseCommerce\PaymentLink\PaymentLinkClient;
use CoinbaseCommerce\PaymentLink\PaymentLink;

// Initialize client with CDP API credentials
$client = new PaymentLinkClient(
    getenv('COINBASE_CDP_KEY_NAME'),      // CDP API key ID
    getenv('COINBASE_CDP_PRIVATE_KEY')     // EC private key PEM
);
PaymentLink::setClient($client);

// Create a payment link
try {
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

    echo "Payment link created!\n";
    echo "  ID: {$result['id']}\n";
    echo "  URL: {$result['url']}\n";
    echo "  Status: {$result['status']}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Get payment link details
try {
    $link = PaymentLink::get($result['id']);
    echo "\nPayment link status: {$link['status']}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// List payment links
try {
    $list = PaymentLink::list(['pageSize' => 10, 'status' => 'ACTIVE']);
    echo "\nActive payment links: " . count($list['paymentLinks']) . "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Deactivate a payment link
try {
    $deactivated = PaymentLink::deactivate($result['id']);
    echo "\nDeactivated: {$deactivated['status']}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
