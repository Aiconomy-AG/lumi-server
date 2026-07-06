<?php

namespace App\Console\Commands;

use App\Exceptions\Shopify\ShopifyException;
use App\Integrations\Shopify\ShopifyConnector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shopify:test-connection')]
#[Description('Test the Shopify API connection using configured credentials')]
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
        $this->info('Testing Shopify connection...');

        try {
            $response = $connector->query([
                'query' => self::TEST_QUERY,
                'variables' => [],
                'operation_name' => 'TestShopifyConnection',
            ]);

            $shop = $response->data['shop'] ?? null;

            if (! is_array($shop)) {
                throw new ShopifyException('Shopify connection test did not return shop data.');
            }

            $name = (string) ($shop['name'] ?? 'unknown');
            $domain = (string) ($shop['myshopifyDomain'] ?? 'unknown');

            $this->components->info("Connected to {$name} ({$domain})");

            return self::SUCCESS;
        } catch (ShopifyException $exception) {
            $this->components->error('Connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
