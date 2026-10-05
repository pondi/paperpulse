<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavedSearch> */
class SavedSearchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'scope' => 'library',
            'filters' => ['type' => 'receipt', 'date_range' => 'this_month'],
            'is_pinned' => true,
        ];
    }
}
