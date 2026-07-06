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
    public function handle(ShopifyConnector $connector): int
    {
        $this->info('Testing Shopify connection...');

        try {
            $shop = $connector->testConnection();

            $this->components->info('Connected successfully!');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Shop ID', (string) $shop->id],
                    ['Name', $shop->name],
                    ['Domain', $shop->domain],
                    ['Email', $shop->email],
                ],
            );

            return self::SUCCESS;
        } catch (ShopifyException $e) {
            $this->components->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
