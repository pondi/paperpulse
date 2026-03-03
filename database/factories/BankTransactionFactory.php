<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, -500, 500);
        $categoryGroup = $this->faker->optional(0.7)->randomElement(TransactionCategory::cases());
        $subcategory = null;

        if ($categoryGroup) {
            $subs = TransactionCategory::subcategories()[$categoryGroup->value] ?? [];
            $subcategory = ! empty($subs) ? $this->faker->randomElement($subs) : null;
        }

        return [
            'user_id' => User::factory(),
            'bank_statement_id' => BankStatement::factory(),
            'transaction_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'posting_date' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'description' => $this->faker->sentence(),
            'reference' => $this->faker->optional()->bothify('REF-#####'),
            'transaction_type' => $amount >= 0 ? 'credit' : 'debit',
            'category' => $this->faker->optional()->word(),
            'category_group' => $categoryGroup,
            'subcategory' => $subcategory,
            'amount' => $amount,
            'balance_after' => $this->faker->randomFloat(2, 500, 5000),
            'currency' => $this->faker->currencyCode(),
            'counterparty_name' => $this->faker->optional()->company(),
            'counterparty_account' => $this->faker->optional()->iban(),
        ];
    }
}
