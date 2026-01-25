<?php

declare(strict_types=1);

namespace Omise\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Omise\Config;
use Omise\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP client for Omise API communication.
 */
class HttpClient
{
    private Client $client;
    private Config $config;
    private LoggerInterface $logger;

    private const USER_AGENT = 'OmisePHP/1.0.0';

    public function __construct(Config $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();

        $this->client = new Client([
            'base_uri' => $config->getApiUrl(),
            'timeout' => $config->getTimeout(),
            'verify' => $config->shouldVerifySsl(),
            'http_errors' => false,
        ]);
    }

    /**
     * Make a GET request.
     *
     * @throws ApiException
     */
    public function get(string $endpoint, array $params = [], bool $usePublicKey = false): array
    {
        return $this->request('GET', $endpoint, ['query' => $params], $usePublicKey);
    }

    /**
     * Make a POST request.
     *
     * @throws ApiException
     */
    public function post(string $endpoint, array $data = [], bool $usePublicKey = false): array
    {
        return $this->request('POST', $endpoint, ['json' => $data], $usePublicKey);
    }

    /**
     * Make a POST request with form data.
     *
     * @throws ApiException
     */
    public function postForm(string $endpoint, array $data = [], bool $usePublicKey = false): array
    {
        return $this->request('POST', $endpoint, ['form_params' => $data], $usePublicKey);
    }

    /**
     * Make a PATCH request.
     *
     * @throws ApiException
     */
    public function patch(string $endpoint, array $data = [], bool $usePublicKey = false): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data], $usePublicKey);
    }

    /**
     * Make a DELETE request.
     *
     * @throws ApiException
     */
    public function delete(string $endpoint, bool $usePublicKey = false): array
    {
        return $this->request('DELETE', $endpoint, [], $usePublicKey);
    }

    /**
     * Make an HTTP request to the Omise API.
     *
     * @throws ApiException
     */
    private function request(string $method, string $endpoint, array $options = [], bool $usePublicKey = false): array
    {
        $key = $usePublicKey ? $this->config->getPublicKey() : $this->config->getSecretKey();

        $defaultOptions = [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'auth' => [$key, ''],
        ];

        $options = array_merge_recursive($defaultOptions, $options);

        $this->logger->debug('Omise API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'use_public_key' => $usePublicKey,
        ]);

        try {
            $response = $this->client->request($method, $endpoint, $options);

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->logger->error('Omise API Request Failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $this->parseResponseBody($response);

                throw new ApiException(
                    message: $body['message'] ?? 'Request failed',
                    httpStatusCode: $response->getStatusCode(),
                    responseBody: $body,
                    previous: $e
                );
            }

            throw new ApiException(
                message: 'Connection error: ' . $e->getMessage(),
                httpStatusCode: 0,
                previous: $e
            );
        } catch (GuzzleException $e) {
            $this->logger->error('Omise API Connection Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException(
                message: 'Connection error: ' . $e->getMessage(),
                httpStatusCode: 0,
                previous: $e
            );
        }
    }

    /**
     * Handle API response.
     *
     * @throws ApiException
     */
    private function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = $this->parseResponseBody($response);

        $this->logger->debug('Omise API Response', [
            'status_code' => $statusCode,
            'object' => $body['object'] ?? 'unknown',
        ]);

        // Check if response contains an error
        if (isset($body['object']) && $body['object'] === 'error') {
            throw ApiException::fromResponse($statusCode, $body);
        }

        // Check for HTTP error status codes
        if ($statusCode >= 400) {
            throw ApiException::fromResponse($statusCode, $body);
        }

        return $body;
    }

    /**
     * Parse response body as JSON.
     */
    private function parseResponseBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if (empty($body)) {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw_body' => $body];
        }

        return $decoded;
    }

    /**
     * Get the underlying Guzzle client.
     */
    public function getGuzzleClient(): Client
    {
        return $this->client;
    }

    /**
     * Set a custom Guzzle client.
     */
    public function setGuzzleClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }
}
