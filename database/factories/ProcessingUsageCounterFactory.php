<?php

namespace Database\Factories;

use App\Models\ProcessingUsageCounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessingUsageCounter>
 */
class ProcessingUsageCounterFactory extends Factory
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
            'scope_key' => fake()->uuid(),
            'usage' => ['calls' => 0, 'reserved_tokens' => 0, 'stages' => []],
            'expires_at' => now()->addDays(2),
        ];
    }
}
