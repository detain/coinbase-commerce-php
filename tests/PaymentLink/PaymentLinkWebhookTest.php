<?php
namespace CoinbaseCommerce\Tests\PaymentLink;

use CoinbaseCommerce\PaymentLink\PaymentLinkWebhook;
use CoinbaseCommerce\Exceptions\SignatureVerificationException;
use CoinbaseCommerce\Exceptions\InvalidResponseException;
use PHPUnit\Framework\TestCase;

class PaymentLinkWebhookTest extends TestCase
{
    private string $secret = 'test-webhook-secret-key';

    private function buildSignatureHeader(string $payload, array $headers, string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $headerNames = implode(' ', array_keys($headers));
        $headerValues = implode('.', array_values($headers));
        $signedPayload = "{$timestamp}.{$headerNames}.{$headerValues}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        return "t={$timestamp},h={$headerNames},v1={$signature}";
    }

    public function testSuccessfulVerification(): void
    {
        $payload = json_encode([
            'eventType' => 'payment_link.payment.success',
            'id' => '69163c762331ed43dc64a6ef',
            'amount' => '100.00',
            'status' => 'COMPLETED',
        ]);
        $requestHeaders = ['content-type' => 'application/json'];
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, $this->secret);

        $event = PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);

        $this->assertEquals('payment_link.payment.success', $event['eventType']);
        $this->assertEquals('COMPLETED', $event['status']);
    }

    public function testRejectsBadSignature(): void
    {
        $payload = json_encode(['eventType' => 'payment_link.payment.success']);
        $requestHeaders = ['content-type' => 'application/json'];
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, 'wrong-secret');

        $this->expectException(SignatureVerificationException::class);
        PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);
    }

    public function testRejectsExpiredTimestamp(): void
    {
        $payload = json_encode(['eventType' => 'payment_link.payment.failed']);
        $requestHeaders = ['content-type' => 'application/json'];
        $oldTimestamp = time() - (10 * 60); // 10 minutes ago
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, $this->secret, $oldTimestamp);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('maximum age');
        PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders, 5);
    }

    public function testRejectsFutureTimestamp(): void
    {
        $payload = json_encode(['eventType' => 'payment_link.payment.success']);
        $requestHeaders = ['content-type' => 'application/json'];
        $futureTimestamp = time() + (10 * 60); // 10 minutes in the future
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, $this->secret, $futureTimestamp);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('future');
        PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);
    }

    public function testCaseInsensitiveHeaderLookup(): void
    {
        $payload = json_encode(['eventType' => 'payment_link.payment.expired']);
        // Signature built with lowercase keys
        $sigHeaderNames = ['content-type' => 'application/json'];
        $sigHeader = $this->buildSignatureHeader($payload, $sigHeaderNames, $this->secret);

        // Request headers with mixed case — should still work
        $requestHeaders = ['Content-Type' => 'application/json'];

        $event = PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);
        $this->assertEquals('payment_link.payment.expired', $event['eventType']);
    }

    public function testRejectsInvalidJson(): void
    {
        $payload = 'not-json{';
        $requestHeaders = ['content-type' => 'application/json'];
        // Build a valid signature for the invalid JSON payload
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, $this->secret);

        $this->expectException(InvalidResponseException::class);
        PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);
    }

    public function testRejectsMalformedSignatureHeader(): void
    {
        $payload = json_encode(['eventType' => 'test']);
        $requestHeaders = ['content-type' => 'application/json'];

        $this->expectException(SignatureVerificationException::class);
        PaymentLinkWebhook::buildEvent($payload, 'garbage-header', $this->secret, $requestHeaders);
    }

    public function testMultipleHeaders(): void
    {
        $payload = json_encode(['eventType' => 'payment_link.payment.success', 'amount' => '50.00']);
        $requestHeaders = ['content-type' => 'application/json', 'x-custom' => 'custom-val'];
        $sigHeader = $this->buildSignatureHeader($payload, $requestHeaders, $this->secret);

        $event = PaymentLinkWebhook::buildEvent($payload, $sigHeader, $this->secret, $requestHeaders);
        $this->assertEquals('payment_link.payment.success', $event['eventType']);
    }
}
