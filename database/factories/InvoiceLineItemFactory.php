<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'line_number' => $this->faker->numberBetween(1, 10),
            'description' => $this->faker->sentence(),
            'sku' => $this->faker->optional()->bothify('SKU-#####'),
            'quantity' => $this->faker->randomFloat(2, 1, 5),
            'unit_of_measure' => $this->faker->optional()->randomElement(['pcs', 'hours', 'kg']),
            'unit_price' => $this->faker->randomFloat(2, 10, 200),
            'discount_percent' => $this->faker->optional()->randomFloat(2, 0, 20),
            'discount_amount' => $this->faker->optional()->randomFloat(2, 0, 50),
            'tax_rate' => $this->faker->optional()->randomFloat(2, 0, 25),
            'tax_amount' => $this->faker->optional()->randomFloat(2, 0, 50),
            'total_amount' => $this->faker->randomFloat(2, 20, 500),
            'category' => $this->faker->optional()->word(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
