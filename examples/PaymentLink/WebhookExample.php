<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use CoinbaseCommerce\PaymentLink\PaymentLinkWebhook;

// Webhook secret from your subscription response
$webhookSecret = getenv('COINBASE_WEBHOOK_SECRET');

// Get the raw request body and headers
$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_HOOK0_SIGNATURE'] ?? '';

// Convert $_SERVER headers to lowercase format
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$headerName] = $value;
    }
}
$headers['content-type'] = $_SERVER['CONTENT_TYPE'] ?? 'application/json';

try {
    $event = PaymentLinkWebhook::buildEvent($payload, $signatureHeader, $webhookSecret, $headers);

    switch ($event['eventType']) {
        case 'payment_link.payment.success':
            // Payment completed — fulfill the order
            echo "Payment completed for link {$event['id']}\n";
            break;
        case 'payment_link.payment.failed':
            echo "Payment failed for link {$event['id']}\n";
            break;
        case 'payment_link.payment.expired':
            echo "Payment expired for link {$event['id']}\n";
            break;
    }

    http_response_code(200);
    echo 'OK';
} catch (\CoinbaseCommerce\Exceptions\SignatureVerificationException $e) {
    http_response_code(400);
    echo 'Invalid signature';
} catch (\Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
