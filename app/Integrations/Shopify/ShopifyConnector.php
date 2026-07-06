<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;
use App\Exceptions\Shopify\ShopifyThrottledException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyConnector
{
    public function __construct(
        private readonly ShopifyConfig $config,
        private readonly ShopifyAccessTokenProvider $tokenProvider,
    ) {}

    public function query(array $payload): ShopifyResponse
    {
        return $this->sendGraphQlRequest($payload, false);
    }

    private function sendGraphQlRequest(array $payload, bool $retried): ShopifyResponse
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->tokenProvider->getAccessToken(),
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(10)
                ->timeout(30)
                ->post($this->config->graphqlUrl(), $this->graphQlBody($payload));
        } catch (ConnectionException) {
            throw new ShopifyException('GraphQL network error.');
        }

        if ($response->status() === 401 && ! $retried) {
            $this->tokenProvider->invalidate();

            return $this->sendGraphQlRequest($payload, true);
        }

        if ($response->status() === 429) {
            throw $this->throttledException($response);
        }

        if ($response->serverError()) {
            throw new ShopifyException('GraphQL server error (HTTP '.$response->status().').');
        }

        if (! $response->successful()) {
            throw new ShopifyException('GraphQL request failed (HTTP '.$response->status().').');
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new ShopifyException('Invalid GraphQL response.');
        }

        return $this->parseGraphQlResponse($decoded);
    }

    private function graphQlBody(array $payload): array
    {
        $body = ['query' => $payload['query']];

        $variables = $payload['variables'] ?? [];

        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        if (! empty($payload['operation_name'])) {
            $body['operationName'] = $payload['operation_name'];
        }

        return $body;
    }

    private function parseGraphQlResponse(array $decoded): ShopifyResponse
    {
        $extensions = is_array($decoded['extensions'] ?? null) ? $decoded['extensions'] : [];
        $errors = $decoded['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $message = null;

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }

                if ($message === null && is_string($error['message'] ?? null) && $error['message'] !== '') {
                    $message = $error['message'];
                }

                if (($error['extensions']['code'] ?? null) === 'THROTTLED') {
                    $throttleStatus = $extensions['cost']['throttleStatus'] ?? [];
                    $requestedQueryCost = (int) ($extensions['cost']['requestedQueryCost'] ?? 0);

                    throw new ShopifyThrottledException(
                        'Throttled.',
                        ShopifyThrottledException::retryDelay(
                            is_array($throttleStatus) ? $throttleStatus : [],
                            $requestedQueryCost,
                        ),
                    );
                }
            }

            throw new ShopifyException($message ?? 'GraphQL error.');
        }

        $data = $decoded['data'] ?? null;

        return new ShopifyResponse(
            data: is_array($data) ? $data : null,
            extensions: $extensions,
        );
    }

    private function throttledException(Response $response): ShopifyThrottledException
    {
        $retryAfter = (int) $response->header('Retry-After');

        if ($retryAfter <= 0) {
            $decoded = $response->json();

            if (is_array($decoded)) {
                $extensions = is_array($decoded['extensions'] ?? null) ? $decoded['extensions'] : [];
                $throttleStatus = $extensions['cost']['throttleStatus'] ?? [];
                $requestedQueryCost = (int) ($extensions['cost']['requestedQueryCost'] ?? 0);

                if (is_array($throttleStatus) && $throttleStatus !== []) {
                    $retryAfter = ShopifyThrottledException::retryDelay($throttleStatus, $requestedQueryCost);
                }
            }
        }

        return new ShopifyThrottledException('Rate limited.', max($retryAfter, 2));
    }
}
