<?php
namespace CoinbaseCommerce\PaymentLink;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use CoinbaseCommerce\Exceptions\ApiException;
use CoinbaseCommerce\Exceptions\AuthenticationException;
use CoinbaseCommerce\Exceptions\InternalServerException;
use CoinbaseCommerce\Exceptions\InvalidRequestException;
use CoinbaseCommerce\Exceptions\RateLimitExceededException;
use CoinbaseCommerce\Exceptions\ResourceNotFoundException;
use CoinbaseCommerce\Exceptions\ServiceUnavailableException;

class PaymentLinkClient
{
    private string $keyName;
    private string $privateKey;
    /** @var \OpenSSLAsymmetricKey|resource */
    private $keyResource;
    private string $baseUrl;
    private int $timeout;
    private ?Client $httpClient = null;

    private static array $errorTypeMap = [
        'unauthorized' => AuthenticationException::class,
        'invalid_request' => InvalidRequestException::class,
        'idempotency_error' => InvalidRequestException::class,
        'not_found' => ResourceNotFoundException::class,
        'rate_limit_exceeded' => RateLimitExceededException::class,
        'internal_server_error' => InternalServerException::class,
        'service_unavailable' => ServiceUnavailableException::class,
    ];

    public function __construct(string $keyName, string $privateKey, array $options = [])
    {
        $this->keyName = $keyName;
        $this->privateKey = $privateKey;

        $this->keyResource = openssl_pkey_get_private($this->privateKey);
        if ($this->keyResource === false) {
            throw new \InvalidArgumentException('Invalid private key provided');
        }

        $this->baseUrl = $options['baseUrl'] ?? 'https://business.coinbase.com/api/v1/';
        if (substr($this->baseUrl, -1) !== '/') {
            $this->baseUrl .= '/';
        }
        $this->timeout = $options['timeout'] ?? 10;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function generateJwt(string $method, string $path): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        $basePath = rtrim(parse_url($this->baseUrl, PHP_URL_PATH), '/');
        $uri = $method . ' ' . $host . $basePath . '/' . ltrim($path, '/');

        $time = time();
        $payload = [
            'sub' => $this->keyName,
            'iss' => 'cdp',
            'nbf' => $time,
            'exp' => $time + 120,
            'uri' => $uri,
        ];
        $headers = [
            'typ' => 'JWT',
            'alg' => 'ES256',
            'kid' => $this->keyName,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->keyResource, 'ES256', $this->keyName, $headers);
    }

    public function get(string $path, array $queryParams = []): array
    {
        return $this->makeRequest('GET', $path, $queryParams);
    }

    public function post(string $path, array $body = [], array $headers = []): array
    {
        return $this->makeRequest('POST', $path, [], $body, $headers);
    }

    public function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client();
        }
        return $this->httpClient;
    }

    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    private function makeRequest(string $method, string $path, array $query = [], array $body = [], array $extraHeaders = []): array
    {
        $jwt = $this->generateJwt($method, $path);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $jwt,
        ];

        $options = [
            'headers' => array_merge($headers, $extraHeaders),
            'timeout' => $this->timeout,
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }
        if (!empty($body)) {
            $options['json'] = $body;
        }

        try {
            $response = $this->getHttpClient()->request($method, $url, $options);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data ?? [];
        } catch (RequestException $e) {
            throw $this->createException($e);
        }
    }

    private function createException(RequestException $e): \Exception
    {
        $response = $e->getResponse();
        $request = $e->getRequest();

        if ($response === null) {
            return new ApiException($e->getMessage(), $request, $response, $e);
        }

        $data = json_decode($response->getBody()->getContents(), true);
        $errorType = $data['errorType'] ?? null;
        $errorMessage = $data['errorMessage'] ?? $e->getMessage();
        $correlationId = $data['correlationId'] ?? null;
        $errorLink = $data['errorLink'] ?? null;

        $message = $errorMessage;
        if ($correlationId) {
            $message .= " [correlationId: {$correlationId}]";
        }
        if ($errorLink) {
            $message .= " [docs: {$errorLink}]";
        }

        $exceptionClass = self::$errorTypeMap[$errorType] ?? ApiException::class;
        return new $exceptionClass($message, $request, $response, $e);
    }
}
