<?php
namespace CoinbaseCommerce\PaymentLink;

class PaymentLink
{
    private static ?PaymentLinkClient $client = null;

    public static function setClient(PaymentLinkClient $client): void
    {
        self::$client = $client;
    }

    public static function create(array $params, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }
        return self::getClient()->post('payment-links', $params, $headers);
    }

    public static function get(string $id): array
    {
        return self::getClient()->get('payment-links/' . $id);
    }

    public static function list(array $params = []): array
    {
        return self::getClient()->get('payment-links', $params);
    }

    public static function deactivate(string $id): array
    {
        return self::getClient()->post('payment-links/' . $id . '/deactivate');
    }

    private static function getClient(): PaymentLinkClient
    {
        if (self::$client === null) {
            throw new \LogicException('PaymentLinkClient not initialized. Call PaymentLink::setClient() first.');
        }
        return self::$client;
    }
}
