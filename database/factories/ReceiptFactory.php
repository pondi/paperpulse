<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory()->state([
                'file_type' => 'receipt',
                'processing_type' => 'receipt',
            ]),
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'merchant_id' => null,
            'category_id' => null,
            'receipt_data' => null,
            'receipt_date' => fake()->optional()->date(),
            'tax_amount' => fake()->optional()->randomFloat(2, 0, 25),
            'total_amount' => fake()->optional()->randomFloat(2, 1, 250),
            'currency' => fake()->optional()->randomElement(['USD', 'EUR', 'GBP']),
            'receipt_category' => fake()->optional()->word(),
            'receipt_description' => fake()->optional()->sentence(),
            'tags' => null,
            'shared_with' => null,
            'ai_entities' => null,
            'language' => null,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
