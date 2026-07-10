<?php

namespace Modules\Sales\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Models\Ingredients;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]

    public function it_can_list_all_products_with_relations_and_limit()
    {

        $category = Category::factory()->create();
        Product::factory()->count(25)->create(['category_id' => $category->id])->each(function ($product) {
            ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);
            $ingredient = Ingredients::factory()->create();
            $product->ingredients()->attach($ingredient);
        });

        $response = $this->getJson('/api/v1/shop/products?limit=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'price', 'variants', 'ingredients']
            ]
        ]);
    }

    #[Test]
    public function it_can_filter_products_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Product::factory()->create(['category_id' => $category1->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        $response = $this->getJson("/api/v1/shop/products?category_id={$category2->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product2->id);
    }

    #[Test]
    public function it_returns_404_if_product_not_found()
    {
        $response = $this->getJson('/api/v1/shop/products/99999');

        $response->assertStatus(404)
            ->assertJson([
                'code' => 'NOT_FOUND',
                'message' => 'Product not found.'
            ]);
    }

    #[Test]
    public function it_can_list_variants_of_a_specific_product()
    {
        $product = Product::factory()->create();
        $variants = ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

        $response = $this->getJson("/api/v1/shop/products/{$product->id}/variants");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'product_id', 'sku', 'stock_quantity']
                ]
            ]);
    }

    #[Test]
    public function it_can_filter_ingredients_by_boolean_flags_and_search()
    {
        Ingredients::factory()->create([
            'name' => 'Almond Milk',
            'is_allergen' => true,
            'is_vegan' => true,
            'is_natural' => true,
        ]);

        Ingredients::factory()->create([
            'name' => 'Heavy Cream',
            'is_allergen' => true,
            'is_vegan' => false,
            'is_natural' => true,
        ]);

        $response = $this->getJson('/api/v1/shop/ingredients?is_vegan=true&search=Almond');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Almond Milk');
    }
}
