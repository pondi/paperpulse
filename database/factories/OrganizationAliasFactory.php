<?php

namespace Database\Factories;

use App\Models\OrganizationAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationAlias>
 */
class OrganizationAliasFactory extends Factory
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
            'kind' => 'property',
            'alias_key' => OrganizationAlias::key(fake()->unique()->streetAddress()),
            'canonical_name' => fake()->streetAddress(),
        ];
    }
}
