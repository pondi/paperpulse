<?php

namespace Database\Factories;

use App\Models\OrganizationInputChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationInputChange>
 */
class OrganizationInputChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'change_key' => 'file:'.fake()->unique()->numberBetween(1, 999999),
            'entity_type' => 'file',
            'entity_id' => fake()->numberBetween(1, 999999),
            'revision' => 1,
        ];
    }
}
