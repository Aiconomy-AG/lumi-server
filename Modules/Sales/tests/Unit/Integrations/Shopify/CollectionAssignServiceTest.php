<?php

namespace Modules\Sales\Tests\Unit\Integrations\Shopify;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Sales\Integrations\Shopify\CategoryCollectionMap;
use Modules\Sales\Integrations\Shopify\CollectionAssignService;
use Modules\Sales\Tests\TestCase;

class CollectionAssignServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureShopify(['shop' => 'test-sales.myshopify.com']);
        Cache::store('array')->flush();

        config([
            'sales.shopify.category_collections' => [
                1 => 'bath',
            ],
        ]);
    }

    public function test_it_resolves_collection_handle_and_adds_products(): void
    {
        Http::fake([
            'test-sales.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-sales.myshopify.com/admin/api/2026-07/graphql.json' => Http::sequence()
                ->push([
                    'data' => [
                        'collectionByHandle' => [
                            'id' => 'gid://shopify/Collection/111',
                            'title' => 'Bath',
                        ],
                    ],
                    'extensions' => [],
                ])
                ->push([
                    'data' => [
                        'collectionAddProducts' => [
                            'collection' => [
                                'id' => 'gid://shopify/Collection/111',
                                'title' => 'Bath',
                            ],
                            'userErrors' => [],
                        ],
                    ],
                    'extensions' => [],
                ]),
        ]);

        $assigned = app(CollectionAssignService::class)->assignProducts(1, [
            'gid://shopify/Product/101',
            'gid://shopify/Product/102',
        ]);

        $this->assertSame(2, $assigned);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['variables']['handle'] ?? null) === 'bath';
        });

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['variables']['id'] ?? null) === 'gid://shopify/Collection/111'
                && ($body['variables']['productIds'] ?? null) === [
                    'gid://shopify/Product/101',
                    'gid://shopify/Product/102',
                ];
        });
    }

    public function test_it_caches_collection_lookup_within_the_same_service_instance(): void
    {
        Http::fake([
            'test-sales.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-sales.myshopify.com/admin/api/2026-07/graphql.json' => Http::sequence()
                ->push([
                    'data' => [
                        'collectionByHandle' => [
                            'id' => 'gid://shopify/Collection/111',
                            'title' => 'Bath',
                        ],
                    ],
                    'extensions' => [],
                ])
                ->push([
                    'data' => [
                        'collectionAddProducts' => [
                            'collection' => [
                                'id' => 'gid://shopify/Collection/111',
                                'title' => 'Bath',
                            ],
                            'userErrors' => [],
                        ],
                    ],
                    'extensions' => [],
                ])
                ->push([
                    'data' => [
                        'collectionAddProducts' => [
                            'collection' => [
                                'id' => 'gid://shopify/Collection/111',
                                'title' => 'Bath',
                            ],
                            'userErrors' => [],
                        ],
                    ],
                    'extensions' => [],
                ]),
        ]);

        $service = new CollectionAssignService(
            app(\Modules\Sales\Integrations\Shopify\ShopifyConnector::class),
            new CategoryCollectionMap,
        );

        $service->assignProducts(1, ['gid://shopify/Product/101']);
        $service->assignProducts(1, ['gid://shopify/Product/102']);

        Http::assertSentCount(4);
    }
}
