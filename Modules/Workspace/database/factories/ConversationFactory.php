<?php

namespace Modules\Workspace\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Workspace\Models\Conversation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'type' => 'direct',
            'name' => null,
            'created_by' => User::factory(),
        ];
    }
}