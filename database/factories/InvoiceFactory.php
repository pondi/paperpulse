<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $fileFactory = File::factory()->state([
            'file_type' => 'invoice',
            'processing_type' => 'invoice',
        ]);

        $invoiceDate = Carbon::instance($this->faker->dateTimeBetween('-3 months', 'now'));
        $dueDate = (clone $invoiceDate)->addDays($this->faker->numberBetween(14, 45));
        $subtotal = $this->faker->randomFloat(2, 100, 2000);
        $taxAmount = round($subtotal * 0.25, 2);
        $total = $subtotal + $taxAmount;
        $amountPaid = $this->faker->randomFloat(2, 0, $total);

        return [
            'file_id' => $fileFactory,
            'user_id' => function (array $attributes) {
                return File::query()->findOrFail($attributes['file_id'])->user_id;
            },
            'merchant_id' => null,
            'category_id' => null,
            'invoice_number' => $this->faker->bothify('INV-#####'),
            'invoice_type' => $this->faker->randomElement(['invoice', 'credit_note', 'debit_note']),
            'from_name' => $this->faker->company(),
            'from_address' => $this->faker->address(),
            'from_vat_number' => $this->faker->optional()->bothify('VAT########'),
            'from_email' => $this->faker->optional()->companyEmail(),
            'from_phone' => $this->faker->optional()->phoneNumber(),
            'to_name' => $this->faker->name(),
            'to_address' => $this->faker->address(),
            'to_vat_number' => $this->faker->optional()->bothify('VAT########'),
            'to_email' => $this->faker->optional()->safeEmail(),
            'to_phone' => $this->faker->optional()->phoneNumber(),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'delivery_date' => $this->faker->optional()->dateTimeBetween($invoiceDate, $dueDate),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $this->faker->optional()->randomFloat(2, 0, 50),
            'shipping_amount' => $this->faker->optional()->randomFloat(2, 0, 50),
            'total_amount' => $total,
            'amount_paid' => $amountPaid,
            'amount_due' => max($total - $amountPaid, 0),
            'currency' => 'NOK',
            'payment_method' => $this->faker->optional()->randomElement(['card', 'bank_transfer', 'cash']),
            'payment_status' => $this->faker->optional()->randomElement(['paid', 'unpaid', 'partial', 'overdue']),
            'payment_terms' => $this->faker->optional()->sentence(),
            'purchase_order_number' => $this->faker->optional()->bothify('PO-#####'),
            'reference_number' => $this->faker->optional()->bothify('REF-#####'),
            'notes' => $this->faker->optional()->sentence(),
            'invoice_data' => null,
        ];
    }
}
