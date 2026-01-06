<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'contract',
            'processing_type' => 'contract',
        ]);

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'contract_number' => $this->faker->optional()->bothify('CT-#####'),
            'contract_title' => $this->faker->sentence(4),
            'contract_type' => $this->faker->randomElement(['employment', 'service', 'rental', 'nda']),
            'parties' => [
                ['name' => $this->faker->company(), 'role' => 'provider'],
                ['name' => $this->faker->name(), 'role' => 'client'],
            ],
            'effective_date' => $this->faker->optional()->dateTimeBetween('-6 months', 'now'),
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'signature_date' => $this->faker->optional()->dateTimeBetween('-6 months', 'now'),
            'duration' => $this->faker->optional()->randomElement(['12 months', '24 months']),
            'renewal_terms' => $this->faker->optional()->sentence(),
            'termination_conditions' => $this->faker->optional()->sentence(),
            'contract_value' => $this->faker->optional()->randomFloat(2, 1000, 100000),
            'currency' => 'NOK',
            'payment_schedule' => $this->faker->optional()->randomElements(
                [
                    ['milestone' => 'Deposit', 'amount' => 1000],
                    ['milestone' => 'Completion', 'amount' => 2000],
                ],
                1
            ),
            'governing_law' => $this->faker->optional()->country(),
            'jurisdiction' => $this->faker->optional()->city(),
            'status' => $this->faker->optional()->randomElement(['draft', 'active', 'expired']),
            'key_terms' => null,
            'obligations' => null,
            'summary' => $this->faker->optional()->sentence(),
            'contract_data' => null,
        ];
    }
}
