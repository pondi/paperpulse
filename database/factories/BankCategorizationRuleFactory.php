<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Models\BankCategorizationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankCategorizationRule>
 */
class BankCategorizationRuleFactory extends Factory
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
            'fingerprint' => hash('sha256', fake()->uuid()),
            'category_group' => TransactionCategory::FoodAndDrink,
            'subcategory' => 'Groceries',
            'source' => 'ai',
        ];
    }
}
