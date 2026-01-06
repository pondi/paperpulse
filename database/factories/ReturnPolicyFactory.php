<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\ReturnPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnPolicy>
 */
class ReturnPolicyFactory extends Factory
{
    protected $model = ReturnPolicy::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'return_policy',
            'processing_type' => 'return_policy',
        ]);

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'receipt_id' => null,
            'invoice_id' => null,
            'merchant_id' => null,
            'return_deadline' => $this->faker->optional()->dateTimeBetween('now', '+60 days'),
            'exchange_deadline' => $this->faker->optional()->dateTimeBetween('now', '+60 days'),
            'conditions' => $this->faker->optional()->paragraph(),
            'refund_method' => $this->faker->optional()->randomElement(['full_refund', 'store_credit', 'exchange_only']),
            'restocking_fee' => $this->faker->optional()->randomFloat(2, 0, 50),
            'restocking_fee_percentage' => $this->faker->optional()->randomFloat(2, 0, 15),
            'is_final_sale' => $this->faker->boolean(10),
            'requires_receipt' => true,
            'requires_original_packaging' => $this->faker->boolean(25),
            'policy_data' => null,
        ];
    }
}
