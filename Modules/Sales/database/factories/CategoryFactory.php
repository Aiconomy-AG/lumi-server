<?php
namespace Modules\Sales\Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\Category;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Sales\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
