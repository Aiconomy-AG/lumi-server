<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;

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

    private const string ONLINE_STORE_PUBLICATIONS_QUERY = <<<'GRAPHQL'
        query OnlineStorePublications {
            publications(first: 5, catalogType: ONLINE_STORE) {
                edges { node { id name } }
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

            $this->reportOnlineStorePublication($connector);

            return self::SUCCESS;
        } catch (ShopifyException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function reportOnlineStorePublication(ShopifyConnector $connector): void
    {
        $configured = config('sales.shopify.online_store_publication_id');

        if (is_string($configured) && trim($configured) !== '') {
            $this->components->info('Online Store publication: '.trim($configured).' (from SHOPIFY_ONLINE_STORE_PUBLICATION_ID)');

            return;
        }

        try {
            $response = $connector->query([
                'query' => self::ONLINE_STORE_PUBLICATIONS_QUERY,
                'operation_name' => 'OnlineStorePublications',
            ]);

            $edges = $response->data['publications']['edges'] ?? [];
            $publication = null;

            foreach ($edges as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;

                if (is_array($node) && isset($node['id'])) {
                    $publication = $node;
                    break;
                }
            }

            if ($publication === null) {
                $this->components->warn(
                    'Online Store publication not found. Enable read_publications/write_publications on the Shopify app '
                    .'or set SHOPIFY_ONLINE_STORE_PUBLICATION_ID.',
                );

                return;
            }

            $this->components->info(sprintf(
                'Online Store publication: %s (%s)',
                (string) ($publication['name'] ?? 'Online Store'),
                (string) $publication['id'],
            ));
        } catch (ShopifyException $exception) {
            $this->components->warn('Could not resolve Online Store publication: '.$exception->getMessage());
        }
    }
}
