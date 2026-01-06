<?php

namespace Database\Factories;

use App\Models\BankStatement;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    protected $model = BankStatement::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'bank_statement',
            'processing_type' => 'bank_statement',
        ]);

        $start = $this->faker->dateTimeBetween('-2 months', '-1 month');
        $end = (clone $start)->modify('+30 days');
        $openingBalance = $this->faker->randomFloat(2, 1000, 5000);
        $closingBalance = $openingBalance + $this->faker->randomFloat(2, -500, 500);

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'bank_name' => $this->faker->company(),
            'account_holder_name' => $this->faker->name(),
            'account_number' => $this->faker->numerify('############'),
            'iban' => $this->faker->iban(),
            'swift_code' => $this->faker->swiftBicNumber(),
            'statement_date' => $end,
            'statement_period_start' => $start,
            'statement_period_end' => $end,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'currency' => 'NOK',
            'total_credits' => $this->faker->randomFloat(2, 0, 10000),
            'total_debits' => $this->faker->randomFloat(2, 0, 10000),
            'transaction_count' => $this->faker->numberBetween(5, 50),
            'statement_data' => null,
        ];
    }
}
