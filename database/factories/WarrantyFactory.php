<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warranty>
 */
class WarrantyFactory extends Factory
{
    protected $model = Warranty::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'warranty',
            'processing_type' => 'warranty',
        ]);

        $startDate = $this->faker->optional()->dateTimeBetween('-1 year', 'now');

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'receipt_id' => null,
            'invoice_id' => null,
            'product_name' => $this->faker->words(3, true),
            'product_category' => $this->faker->word(),
            'manufacturer' => $this->faker->company(),
            'model_number' => $this->faker->bothify('MOD-###'),
            'serial_number' => $this->faker->bothify('SN-########'),
            'purchase_date' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'warranty_start_date' => $startDate,
            'warranty_end_date' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'warranty_duration' => $this->faker->optional()->randomElement(['12 months', '24 months', '36 months']),
            'warranty_type' => $this->faker->optional()->randomElement(['manufacturer', 'extended', 'store']),
            'warranty_provider' => $this->faker->company(),
            'warranty_number' => $this->faker->bothify('WAR-#####'),
            'coverage_type' => $this->faker->optional()->randomElement(['full', 'limited', 'parts_only']),
            'coverage_description' => $this->faker->optional()->sentence(),
            'exclusions' => $this->faker->optional()->sentence(),
            'support_phone' => $this->faker->optional()->phoneNumber(),
            'support_email' => $this->faker->optional()->companyEmail(),
            'support_website' => $this->faker->optional()->url(),
            'warranty_data' => null,
        ];
    }
}
