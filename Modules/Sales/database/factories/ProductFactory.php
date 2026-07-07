<?php

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Enums\ShopifySyncStatus;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 200),
            'image_url' => 'https://www.w3schools.com/images/w3schools_green.jpg',
            'category_id' => Category::query()->inRandomOrder()->value('id')
                ?? Category::query()->create(['name' => fake()->word()])->getKey(),
            'shopify_sync_status' => ShopifySyncStatus::Unsynced,
        ];
    }
}
