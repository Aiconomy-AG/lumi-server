<?php

namespace Modules\Sales\Tests\Unit\Integrations\Shopify;

use Modules\Sales\Integrations\Shopify\CollectionAssignService;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductSyncServiceInventoryTest extends TestCase
{
    private function serviceWithLocation(): ProductSyncService
    {
        $service = new ProductSyncService(
            $this->createMock(ShopifyConnector::class),
            $this->createMock(CollectionAssignService::class),
        );

        $property = new \ReflectionProperty(ProductSyncService::class, 'inventoryLocationId');
        $property->setAccessible(true);
        $property->setValue($service, 'gid://shopify/Location/1');

        return $service;
    }

    public function test_build_variant_enables_tracking_and_pushes_stock(): void
    {
        $service = $this->serviceWithLocation();

        $variant = new ProductVariant([
            'sku' => 'SKU-1',
            'price' => 12.5,
            'stock_quantity' => 42,
        ]);

        $product = new Product(['price' => 12.5]);

        $method = new ReflectionMethod(ProductSyncService::class, 'buildVariant');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $service,
            $variant,
            $product,
            [['optionName' => 'Title', 'name' => 'Default Title']],
        );

        $this->assertTrue($payload['inventoryItem']['tracked']);
        $this->assertSame([[
            'locationId' => 'gid://shopify/Location/1',
            'name' => 'available',
            'quantity' => 42,
        ]], $payload['inventoryQuantities']);
    }

    public function test_build_variant_includes_weight_on_inventory_item(): void
    {
        $service = $this->serviceWithLocation();

        $variant = new ProductVariant([
            'sku' => 'SKU-2',
            'price' => 10,
            'weight' => 250,
            'weight_unit' => 'g',
            'stock_quantity' => 5,
        ]);

        $product = new Product(['price' => 10]);

        $method = new ReflectionMethod(ProductSyncService::class, 'buildVariant');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $service,
            $variant,
            $product,
            [['optionName' => 'Size', 'name' => '250g']],
        );

        $this->assertSame('GRAMS', $payload['inventoryItem']['measurement']['weight']['unit']);
        $this->assertSame(250.0, $payload['inventoryItem']['measurement']['weight']['value']);
    }
}
