<?php
namespace Modules\Sales\Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Sales\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->bothify('VAR-####-????'),
            'price' => fake()->randomFloat(2, 5, 150),
            'stock_quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
