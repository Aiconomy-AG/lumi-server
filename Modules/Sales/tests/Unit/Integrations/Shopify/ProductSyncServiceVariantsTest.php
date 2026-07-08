<?php

namespace Modules\Sales\Tests\Unit\Integrations\Shopify;

use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\CollectionAssignService;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductSyncServiceVariantsTest extends TestCase
{
    private function service(): ProductSyncService
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

    public function test_build_variants_throws_when_product_has_no_variants(): void
    {
        $service = $this->service();
        $product = new Product(['price' => 10]);
        $product->setRelation('variants', collect());

        $method = new ReflectionMethod(ProductSyncService::class, 'buildVariants');
        $method->setAccessible(true);

        $this->expectException(ShopifyException::class);
        $this->expectExceptionMessage('no variants');

        $method->invoke($service, $product);
    }

    public function test_single_colour_variant_uses_default_title_option(): void
    {
        $service = $this->service();

        $product = new Product(['price' => 10]);
        $product->setRelation('variants', collect([
            new ProductVariant([
                'sku' => 'RED-1',
                'colour' => 'Red',
                'price' => 10,
                'stock_quantity' => 5,
            ]),
        ]));

        $method = new ReflectionMethod(ProductSyncService::class, 'buildVariants');
        $method->setAccessible(true);

        [$productOptions, $variantInputs] = $method->invoke($service, $product);

        $this->assertSame('Title', $productOptions[0]['name']);
        $this->assertSame('Default Title', $variantInputs[0]['optionValues'][0]['name']);
        $this->assertSame('RED-1', $variantInputs[0]['sku']);
    }

    public function test_multiple_colour_variants_use_color_option(): void
    {
        $service = $this->service();

        $product = new Product(['price' => 10]);
        $product->setRelation('variants', collect([
            new ProductVariant(['sku' => 'RED-1', 'colour' => 'Red', 'price' => 10, 'stock_quantity' => 1]),
            new ProductVariant(['sku' => 'BLU-1', 'colour' => 'Blue', 'price' => 10, 'stock_quantity' => 2]),
        ]));

        $method = new ReflectionMethod(ProductSyncService::class, 'buildVariants');
        $method->setAccessible(true);

        [$productOptions, $variantInputs] = $method->invoke($service, $product);

        $this->assertSame('Color', $productOptions[0]['name']);
        $this->assertCount(2, $variantInputs);
        $this->assertSame('Red', $variantInputs[0]['optionValues'][0]['name']);
        $this->assertSame('Blue', $variantInputs[1]['optionValues'][0]['name']);
    }
}
