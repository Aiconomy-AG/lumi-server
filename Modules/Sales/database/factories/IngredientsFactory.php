<?php
namespace Modules\Sales\Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\Ingredients;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Sales\Models\Ingredients>
 */
class IngredientsFactory extends Factory
{
    protected $model = Ingredients::class;
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_allergen' => fake()->boolean(),
            'is_vegan' => fake()->boolean(),
            'is_natural' => fake()->boolean(),
        ];
    }
}
