<?php

namespace Tests\Unit\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;
use App\Exceptions\Shopify\ShopifyThrottledException;
use App\Integrations\Shopify\ShopifyConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureShopify();
        Cache::store('array')->flush();
    }

    public function test_it_sends_a_successful_graphql_request(): void
    {
        $this->fakeTokenAndGraphql(
            graphQlResponse: [
                'data' => [
                    'shop' => [
                        'name' => 'Test Shop',
                        'myshopifyDomain' => 'test-shop.myshopify.com',
                    ],
                ],
                'extensions' => [
                    'cost' => [
                        'requestedQueryCost' => 1,
                        'throttleStatus' => [
                            'currentlyAvailable' => 999,
                            'restoreRate' => 50,
                        ],
                    ],
                ],
            ],
        );

        $response = app(ShopifyConnector::class)->query([
            'query' => 'query { shop { name } }',
            'variables' => ['limit' => 1],
            'operation_name' => 'ShopQuery',
        ]);

        $this->assertSame('Test Shop', $response->data['shop']['name']);
        $this->assertSame(1, $response->cost()['requestedQueryCost']);
        $this->assertSame(999, $response->throttleStatus()['currentlyAvailable']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://test-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && $body['operationName'] === 'ShopQuery'
                && $body['variables'] === ['limit' => 1];
        });
    }

    public function test_it_throws_for_graphql_errors(): void
    {
        $this->fakeTokenAndGraphql(
            graphQlResponse: [
                'errors' => [
                    [
                        'message' => 'Field error',
                        'path' => ['shop', 'name'],
                        'extensions' => ['code' => 'ACCESS_DENIED'],
                    ],
                ],
            ],
        );

        $this->expectException(ShopifyException::class);
        $this->expectExceptionMessage('Shopify GraphQL request failed: Field error');

        app(ShopifyConnector::class)->query([
            'query' => 'query { shop { name } }',
        ]);
    }

    public function test_it_throws_throttled_exception_for_http_429(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response(
                ['errors' => [['message' => 'Throttled']]],
                429,
                ['Retry-After' => '7'],
            ),
        ]);

        try {
            app(ShopifyConnector::class)->query(['query' => 'query { shop { name } }']);
            $this->fail('Expected ShopifyThrottledException was not thrown.');
        } catch (ShopifyThrottledException $exception) {
            $this->assertSame(7, $exception->retryAfterSeconds());
        }
    }

    public function test_it_throws_throttled_exception_for_graphql_throttled_error(): void
    {
        $this->fakeTokenAndGraphql(
            graphQlResponse: [
                'errors' => [
                    [
                        'message' => 'Throttled',
                        'extensions' => ['code' => 'THROTTLED'],
                    ],
                ],
                'extensions' => [
                    'cost' => [
                        'requestedQueryCost' => 100,
                        'throttleStatus' => [
                            'currentlyAvailable' => 10,
                            'restoreRate' => 50,
                        ],
                    ],
                ],
            ],
        );

        try {
            app(ShopifyConnector::class)->query(['query' => 'query { shop { name } }']);
            $this->fail('Expected ShopifyThrottledException was not thrown.');
        } catch (ShopifyThrottledException $exception) {
            $this->assertSame(2, $exception->retryAfterSeconds());
        }
    }

    public function test_it_refreshes_token_and_retries_once_on_http_401(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::sequence()
                ->push(['access_token' => 'shpat_expired', 'expires_in' => 3600])
                ->push(['access_token' => 'shpat_fresh', 'expires_in' => 3600]),
            'test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::sequence()
                ->push(status: 401)
                ->push([
                    'data' => ['shop' => ['name' => 'Recovered Shop']],
                    'extensions' => [],
                ]),
        ]);

        $response = app(ShopifyConnector::class)->query([
            'query' => 'query { shop { name } }',
        ]);

        $this->assertSame('Recovered Shop', $response->data['shop']['name']);
        Http::assertSentCount(4);
    }

    public function test_it_throws_shopify_exception_for_server_errors(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response('error', 503),
        ]);

        $this->expectException(ShopifyException::class);

        app(ShopifyConnector::class)->query(['query' => 'query { shop { name } }']);
    }

    public function test_it_omits_empty_variables_from_graphql_request(): void
    {
        $this->fakeTokenAndGraphql(
            graphQlResponse: [
                'data' => ['shop' => ['name' => 'Test Shop']],
                'extensions' => [],
            ],
        );

        app(ShopifyConnector::class)->query([
            'query' => 'query { shop { name } }',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ! array_key_exists('variables', $body);
        });
    }

    /**
     * @param  array<string, mixed>  $graphQlResponse
     */
    private function fakeTokenAndGraphql(array $graphQlResponse): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response($graphQlResponse),
        ]);
    }
}
