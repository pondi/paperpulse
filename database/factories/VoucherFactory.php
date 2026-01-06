<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'voucher',
            'processing_type' => 'voucher',
        ]);

        $issueDate = $this->faker->optional()->dateTimeBetween('-1 year', 'now');

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'merchant_id' => null,
            'voucher_type' => $this->faker->randomElement(['gift_card', 'payment_plan', 'store_credit', 'coupon', 'promo_code']),
            'code' => $this->faker->optional()->bothify('VC-####-####'),
            'barcode' => $this->faker->optional()->ean13(),
            'qr_code' => null,
            'issue_date' => $issueDate,
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'original_value' => $this->faker->randomFloat(2, 10, 500),
            'current_value' => $this->faker->randomFloat(2, 0, 500),
            'currency' => 'NOK',
            'installment_count' => $this->faker->optional()->numberBetween(1, 12),
            'monthly_payment' => $this->faker->optional()->randomFloat(2, 10, 200),
            'first_payment_date' => $issueDate,
            'final_payment_date' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'is_redeemed' => false,
            'redeemed_at' => null,
            'redemption_location' => $this->faker->optional()->city(),
            'terms_and_conditions' => $this->faker->optional()->sentence(),
            'restrictions' => $this->faker->optional()->sentence(),
            'voucher_data' => null,
        ];
    }
}
