<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;
use App\Exceptions\Shopify\ShopifyThrottledException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyConnector
{
    private const int CONNECT_TIMEOUT_SECONDS = 10;

    private const int REQUEST_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly ShopifyConfig $config,
        private readonly ShopifyAccessTokenProvider $tokenProvider,
    ) {}

    /**
     * @param  array{query: string, variables?: array<string, mixed>, operation_name?: string|null}  $payload
     */
    public function query(array $payload): ShopifyResponse
    {
        return $this->sendGraphQlRequest($payload, hasRetriedAuthentication: false);
    }

    /**
     * @param  array{query: string, variables?: array<string, mixed>, operation_name?: string|null}  $payload
     */
    private function sendGraphQlRequest(array $payload, bool $hasRetriedAuthentication): ShopifyResponse
    {
        $body = $this->buildGraphQlBody($payload);

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->tokenProvider->getAccessToken(),
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post($this->config->graphqlUrl(), $body);
        } catch (ConnectionException) {
            throw new ShopifyException('Shopify GraphQL request failed due to a network error.');
        }

        if ($response->status() === 401 && ! $hasRetriedAuthentication) {
            $this->tokenProvider->invalidate();

            return $this->sendGraphQlRequest($payload, hasRetriedAuthentication: true);
        }

        if ($response->status() === 429) {
            throw $this->buildThrottledException($response);
        }

        if ($response->serverError()) {
            throw new ShopifyException(sprintf(
                'Shopify GraphQL request failed with a server error (HTTP %d).',
                $response->status(),
            ));
        }

        if (! $response->successful()) {
            throw new ShopifyException(
                'Shopify GraphQL request failed (HTTP '.$response->status().').'
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new ShopifyException('Shopify GraphQL response was not valid JSON.');
        }

        return $this->parseGraphQlResponse($decoded);
    }

    /**
     * @param  array{query: string, variables?: array<string, mixed>, operation_name?: string|null}  $payload
     * @return array<string, mixed>
     */
    private function buildGraphQlBody(array $payload): array
    {
        $body = [
            'query' => $payload['query'],
        ];

        $variables = $payload['variables'] ?? [];

        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        if (! empty($payload['operation_name'])) {
            $body['operationName'] = $payload['operation_name'];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function parseGraphQlResponse(array $decoded): ShopifyResponse
    {
        $extensions = is_array($decoded['extensions'] ?? null) ? $decoded['extensions'] : [];
        $errors = $decoded['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $firstErrorMessage = null;

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }

                if ($firstErrorMessage === null && is_string($error['message'] ?? null) && $error['message'] !== '') {
                    $firstErrorMessage = $error['message'];
                }

                $code = $error['extensions']['code'] ?? null;

                if ($code === 'THROTTLED') {
                    $throttleStatus = $extensions['cost']['throttleStatus'] ?? [];
                    $requestedQueryCost = (int) ($extensions['cost']['requestedQueryCost'] ?? 0);

                    throw new ShopifyThrottledException(
                        'Shopify GraphQL request was throttled.',
                        ShopifyThrottledException::calculateRetryDelay(
                            is_array($throttleStatus) ? $throttleStatus : [],
                            $requestedQueryCost,
                        ),
                    );
                }
            }

            throw new ShopifyException(
                $firstErrorMessage === null
                    ? 'Shopify GraphQL request returned errors.'
                    : 'Shopify GraphQL request failed: '.$firstErrorMessage
            );
        }

        $data = $decoded['data'] ?? null;

        return new ShopifyResponse(
            data: is_array($data) ? $data : null,
            extensions: $extensions,
        );
    }

    private function buildThrottledException(Response $response): ShopifyThrottledException
    {
        $retryAfter = (int) $response->header('Retry-After');

        if ($retryAfter <= 0) {
            $decoded = $response->json();

            if (is_array($decoded)) {
                $extensions = is_array($decoded['extensions'] ?? null) ? $decoded['extensions'] : [];
                $throttleStatus = $extensions['cost']['throttleStatus'] ?? [];
                $requestedQueryCost = (int) ($extensions['cost']['requestedQueryCost'] ?? 0);

                if (is_array($throttleStatus) && $throttleStatus !== []) {
                    $retryAfter = ShopifyThrottledException::calculateRetryDelay(
                        $throttleStatus,
                        $requestedQueryCost,
                    );
                }
            }
        }

        if ($retryAfter <= 0) {
            $retryAfter = 2;
        }

        return new ShopifyThrottledException(
            'Shopify GraphQL request was rate limited (HTTP 429).',
            $retryAfter,
        );
    }
}
