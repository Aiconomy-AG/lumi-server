<?php

namespace Modules\sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\shop\Exceptions\Shopify\ShopifyException;
use Modules\shop\Integrations\Shopify\ShopifyConnector;

#[Signature('shopify:test-connection')]
#[Description('Test Shopify API credentials')]
class ShopifyTestConnection extends Command
{
    private const string TEST_QUERY = <<<'GRAPHQL'
        query TestShopifyConnection {
            shop {
                name
                myshopifyDomain
            }
        }
        GRAPHQL;

    public function handle(ShopifyConnector $connector): int
    {
        try {
            $response = $connector->query([
                'query' => self::TEST_QUERY,
                'operation_name' => 'TestShopifyConnection',
            ]);

            $shop = $response->data['shop'] ?? null;

            if (! is_array($shop)) {
                throw new ShopifyException('No shop data in response.');
            }

            $name = (string) ($shop['name'] ?? 'unknown');
            $domain = (string) ($shop['myshopifyDomain'] ?? 'unknown');

            $this->components->info("Connected to {$name} ({$domain})");

            return self::SUCCESS;
        } catch (ShopifyException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
