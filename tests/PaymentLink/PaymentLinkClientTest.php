<?php
namespace CoinbaseCommerce\Tests\PaymentLink;

use CoinbaseCommerce\PaymentLink\PaymentLinkClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

class PaymentLinkClientTest extends TestCase
{
    private string $keyName;
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        // Generate a fresh EC key pair for testing
        // Find an openssl.cnf file for environments where OPENSSL_CONF is not set (e.g. Windows)
        $keyConfig = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if (empty(getenv('OPENSSL_CONF'))) {
            foreach ([
                'C:/Program Files/NuSphere/PhpED/php82/extras/ssl/openssl.cnf',
                'C:/xampp/php/extras/ssl/openssl.cnf',
                'C:/Program Files/Common Files/SSL/openssl.cnf',
            ] as $candidate) {
                if (file_exists($candidate)) {
                    $keyConfig['config'] = $candidate;
                    break;
                }
            }
        }
        $keyResource = openssl_pkey_new($keyConfig);
        if ($keyResource === false) {
            $this->markTestSkipped('Could not generate EC key pair: openssl not properly configured');
        }
        $privateKeyStr = '';
        openssl_pkey_export($keyResource, $privateKeyStr, null, $keyConfig);
        $this->privateKey = $privateKeyStr;
        $details = openssl_pkey_get_details($keyResource);
        $this->publicKey = $details['key'];
        $this->keyName = 'test-key-id';
    }

    public function testGenerateJwtContainsCorrectClaims(): void
    {
        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $jwt = $client->generateJwt('GET', 'payment-links');

        $decoded = JWT::decode($jwt, new Key($this->publicKey, 'ES256'));

        $this->assertEquals($this->keyName, $decoded->sub);
        $this->assertEquals('cdp', $decoded->iss);
        $this->assertStringContainsString('GET business.coinbase.com/api/v1/payment-links', $decoded->uri);
        $this->assertLessThanOrEqual(time() + 120, $decoded->exp);
        $this->assertGreaterThanOrEqual(time() - 5, $decoded->nbf);
    }

    public function testGenerateJwtHasCorrectHeaders(): void
    {
        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $jwt = $client->generateJwt('POST', 'payment-links');

        // Decode header without verification
        $parts = explode('.', $jwt);
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        $this->assertEquals('JWT', $header['typ']);
        $this->assertEquals('ES256', $header['alg']);
        $this->assertEquals($this->keyName, $header['kid']);
        $this->assertArrayHasKey('nonce', $header);
        $this->assertEquals(32, strlen($header['nonce']));
    }

    public function testConstructorThrowsOnInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentLinkClient($this->keyName, 'not-a-valid-key');
    }

    public function testConstructorSetsDefaults(): void
    {
        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $this->assertEquals('https://business.coinbase.com/api/v1/', $client->getBaseUrl());
        $this->assertEquals(10, $client->getTimeout());
    }

    public function testConstructorAcceptsOptions(): void
    {
        $client = new PaymentLinkClient($this->keyName, $this->privateKey, [
            'baseUrl' => 'https://custom.example.com/api/',
            'timeout' => 30,
        ]);
        $this->assertEquals('https://custom.example.com/api/', $client->getBaseUrl());
        $this->assertEquals(30, $client->getTimeout());
    }

    public function testGetSendsCorrectRequest(): void
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['id' => 'abc123', 'status' => 'ACTIVE'])),
        ]);
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handler = \GuzzleHttp\HandlerStack::create($mock);
        $handler->push($history);
        $mockClient = new \GuzzleHttp\Client(['handler' => $handler]);

        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $client->setHttpClient($mockClient);

        $result = $client->get('payment-links/abc123');

        $this->assertEquals('abc123', $result['id']);
        $request = $container[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('payment-links/abc123', $request->getUri()->getPath());
        $this->assertStringContainsString('Bearer ', $request->getHeaderLine('Authorization'));
    }

    public function testPostSendsBodyAndIdempotencyKey(): void
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => 'new123', 'url' => 'https://pay.coinbase.com/pl_test'])),
        ]);
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handler = \GuzzleHttp\HandlerStack::create($mock);
        $handler->push($history);
        $mockClient = new \GuzzleHttp\Client(['handler' => $handler]);

        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $client->setHttpClient($mockClient);

        $result = $client->post('payment-links', ['amount' => '100.00', 'currency' => 'USDC'], ['X-Idempotency-Key' => 'test-uuid']);

        $this->assertEquals('new123', $result['id']);
        $request = $container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('test-uuid', $request->getHeaderLine('X-Idempotency-Key'));
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals('100.00', $body['amount']);
    }

    public function testErrorResponseThrowsCorrectException(): void
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(401, [], json_encode([
                'errorType' => 'unauthorized',
                'errorMessage' => 'Not authenticated',
                'correlationId' => 'abc-123',
            ])),
        ]);
        $handler = \GuzzleHttp\HandlerStack::create($mock);
        $mockClient = new \GuzzleHttp\Client(['handler' => $handler, 'http_errors' => true]);

        $client = new PaymentLinkClient($this->keyName, $this->privateKey);
        $client->setHttpClient($mockClient);

        $this->expectException(\CoinbaseCommerce\Exceptions\AuthenticationException::class);
        $this->expectExceptionMessage('Not authenticated');
        $client->get('payment-links');
    }
}
