<?php
namespace CoinbaseCommerce\Tests\PaymentLink;

use CoinbaseCommerce\PaymentLink\PaymentLink;
use CoinbaseCommerce\PaymentLink\PaymentLinkClient;
use PHPUnit\Framework\TestCase;

class PaymentLinkTest extends TestCase
{
    private PaymentLinkClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(PaymentLinkClient::class);
        PaymentLink::setClient($this->client);
    }

    public function testCreateCallsPostWithCorrectPath(): void
    {
        $params = ['amount' => '50.00', 'currency' => 'USDC', 'description' => 'Test payment'];
        $expected = ['id' => 'abc123def456', 'url' => 'https://pay.coinbase.com/pl_test', 'status' => 'ACTIVE'];

        $this->client->expects($this->once())
            ->method('post')
            ->with('payment-links', $params, [])
            ->willReturn($expected);

        $result = PaymentLink::create($params);
        $this->assertEquals($expected, $result);
    }

    public function testCreatePassesIdempotencyKey(): void
    {
        $params = ['amount' => '50.00', 'currency' => 'USDC'];
        $idempotencyKey = '8e03978e-40d5-43e8-bc93-6894a57f9324';

        $this->client->expects($this->once())
            ->method('post')
            ->with('payment-links', $params, ['X-Idempotency-Key' => $idempotencyKey])
            ->willReturn(['id' => 'abc']);

        PaymentLink::create($params, $idempotencyKey);
    }

    public function testGetCallsGetWithId(): void
    {
        $id = '68f7a946db0529ea9b6d3a12';
        $expected = ['id' => $id, 'status' => 'COMPLETED'];

        $this->client->expects($this->once())
            ->method('get')
            ->with('payment-links/' . $id)
            ->willReturn($expected);

        $result = PaymentLink::get($id);
        $this->assertEquals($expected, $result);
    }

    public function testListCallsGetWithQueryParams(): void
    {
        $params = ['pageSize' => 10, 'status' => 'ACTIVE'];
        $expected = ['paymentLinks' => [['id' => 'a']], 'nextPageToken' => 'tok123'];

        $this->client->expects($this->once())
            ->method('get')
            ->with('payment-links', $params)
            ->willReturn($expected);

        $result = PaymentLink::list($params);
        $this->assertEquals($expected, $result);
    }

    public function testDeactivateCallsPostToDeactivatePath(): void
    {
        $id = '68f7a946db0529ea9b6d3a12';
        $expected = ['id' => $id, 'status' => 'DEACTIVATED'];

        $this->client->expects($this->once())
            ->method('post')
            ->with('payment-links/' . $id . '/deactivate')
            ->willReturn($expected);

        $result = PaymentLink::deactivate($id);
        $this->assertEquals($expected, $result);
    }

    public function testThrowsIfClientNotSet(): void
    {
        // Reset static client
        $reflection = new \ReflectionClass(PaymentLink::class);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PaymentLinkClient not initialized');
        PaymentLink::create(['amount' => '10', 'currency' => 'USDC']);
    }
}
