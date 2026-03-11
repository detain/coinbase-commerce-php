<?php
namespace CoinbaseCommerce\PaymentLink;

use CoinbaseCommerce\Exceptions\InvalidResponseException;
use CoinbaseCommerce\Exceptions\SignatureVerificationException;

class PaymentLinkWebhook
{
    public static function buildEvent(
        string $payload,
        string $signatureHeader,
        string $secret,
        array $requestHeaders,
        int $maxAgeMinutes = 5
    ): array {
        // Verify signature BEFORE parsing JSON (don't leak payload validity to unauthenticated callers)
        self::verifySignature($payload, $signatureHeader, $secret, $requestHeaders, $maxAgeMinutes);

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidResponseException('Invalid payload provided. No JSON object could be decoded.', $payload);
        }

        return $data;
    }

    public static function verifySignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        array $requestHeaders,
        int $maxAgeMinutes = 5
    ): void {
        // Parse signature header: t=timestamp,h=headerNames,v1=signature
        $elements = explode(',', $signatureHeader);
        $parsed = [];
        foreach ($elements as $element) {
            $pos = strpos($element, '=');
            if ($pos === false) {
                throw new SignatureVerificationException('Malformed webhook signature header: unable to parse');
            }
            $key = substr($element, 0, $pos);
            $value = substr($element, $pos + 1);
            $parsed[$key] = $value;
        }

        if (!isset($parsed['t'], $parsed['h'], $parsed['v1'])) {
            throw new SignatureVerificationException('Malformed webhook signature header: missing required fields (t, h, v1)');
        }

        $timestamp = $parsed['t'];
        $headerNames = $parsed['h'];
        $providedSignature = $parsed['v1'];

        // Replay protection (check before expensive HMAC)
        $webhookTime = (int) $timestamp;
        $currentTime = time();
        $ageMinutes = ($currentTime - $webhookTime) / 60;

        if ($ageMinutes > $maxAgeMinutes) {
            throw new SignatureVerificationException(
                sprintf('Webhook timestamp exceeds maximum age: %.1f minutes > %d minutes', $ageMinutes, $maxAgeMinutes)
            );
        }

        if ($ageMinutes < -1) {
            throw new SignatureVerificationException('Webhook timestamp is in the future');
        }

        // Normalize request headers to lowercase keys
        $normalizedHeaders = [];
        foreach ($requestHeaders as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        // Build header values string
        $headerNameList = explode(' ', $headerNames);
        $headerValues = [];
        foreach ($headerNameList as $name) {
            $headerValues[] = $normalizedHeaders[strtolower($name)] ?? '';
        }
        $headerValuesStr = implode('.', $headerValues);

        // Build signed payload
        $signedPayload = "{$timestamp}.{$headerNames}.{$headerValuesStr}.{$payload}";

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Timing-safe comparison
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new SignatureVerificationException($expectedSignature, $payload);
        }
    }
}
