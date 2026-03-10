<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Services\Factories\Concerns\ResolvesMerchant;
use App\Services\Receipt\ReceiptEnricherService;
use Illuminate\Database\Eloquent\Model;

class InvoiceFactory extends BaseEntityFactory
{
    use ResolvesMerchant;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function fields(): array
    {
        return [
            'merchant_id',
            'category_id',
            'invoice_number',
            'invoice_type',
            'from_name',
            'from_address',
            'from_vat_number',
            'from_email',
            'from_phone',
            'to_name',
            'to_address',
            'to_vat_number',
            'to_email',
            'to_phone',
            'invoice_date',
            'due_date',
            'delivery_date',
            'subtotal',
            'tax_amount',
            'discount_amount',
            'shipping_amount',
            'total_amount',
            'amount_paid',
            'amount_due',
            'currency',
            'payment_method',
            'payment_status',
            'payment_terms',
            'purchase_order_number',
            'reference_number',
            'notes',
        ];
    }

    protected function dateFields(): array
    {
        return ['invoice_date', 'due_date', 'delivery_date'];
    }

    protected function defaults(): array
    {
        return [
            'invoice_type' => 'invoice',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_due' => 0,
            'currency' => 'NOK',
        ];
    }

    protected function rawDataField(): ?string
    {
        return 'invoice_data';
    }

    protected function prepareData(array $data, File $file): array
    {
        $vendor = $data['vendor'] ?? [];
        $customer = $data['customer'] ?? [];
        $invoiceInfo = $data['invoice_info'] ?? [];
        $totals = $data['totals'] ?? [];
        $payment = $data['payment'] ?? [];

        return array_merge($data, [
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'invoice_number' => $invoiceInfo['invoice_number'] ?? $data['invoice_number'] ?? null,
            'invoice_type' => $invoiceInfo['invoice_type'] ?? $data['invoice_type'] ?? null,
            'from_name' => $vendor['name'] ?? $data['from_name'] ?? null,
            'from_address' => $vendor['address'] ?? $data['from_address'] ?? null,
            'from_vat_number' => $vendor['vat_number'] ?? $data['from_vat_number'] ?? null,
            'from_email' => $vendor['email'] ?? $data['from_email'] ?? null,
            'from_phone' => $vendor['phone'] ?? $data['from_phone'] ?? null,
            'to_name' => $customer['name'] ?? $data['to_name'] ?? null,
            'to_address' => $customer['address'] ?? $data['to_address'] ?? null,
            'to_vat_number' => $customer['vat_number'] ?? $data['to_vat_number'] ?? null,
            'to_email' => $customer['email'] ?? $data['to_email'] ?? null,
            'to_phone' => $customer['phone'] ?? $data['to_phone'] ?? null,
            'invoice_date' => $invoiceInfo['invoice_date'] ?? $data['invoice_date'] ?? null,
            'due_date' => $invoiceInfo['due_date'] ?? $data['due_date'] ?? null,
            'delivery_date' => $invoiceInfo['delivery_date'] ?? $data['delivery_date'] ?? null,
            'subtotal' => $totals['subtotal'] ?? $data['subtotal'] ?? null,
            'tax_amount' => $totals['tax_amount'] ?? $data['tax_amount'] ?? null,
            'discount_amount' => $totals['discount_amount'] ?? $data['discount_amount'] ?? null,
            'shipping_amount' => $totals['shipping_amount'] ?? $data['shipping_amount'] ?? null,
            'total_amount' => $totals['total_amount'] ?? $data['total_amount'] ?? null,
            'amount_paid' => $totals['amount_paid'] ?? $data['amount_paid'] ?? null,
            'amount_due' => $totals['amount_due'] ?? $data['amount_due'] ?? null,
            'currency' => $payment['currency'] ?? $data['currency'] ?? null,
            'payment_method' => $payment['method'] ?? $data['payment_method'] ?? null,
            'payment_status' => $payment['status'] ?? $data['payment_status'] ?? null,
            'payment_terms' => $payment['terms'] ?? $data['payment_terms'] ?? null,
            'purchase_order_number' => $invoiceInfo['purchase_order_number'] ?? $data['purchase_order_number'] ?? null,
            'reference_number' => $invoiceInfo['reference_number'] ?? $data['reference_number'] ?? null,
        ]);
    }

    protected function afterCreate(Model $model, array $data, File $file): void
    {
        $lineItems = $data['line_items'] ?? [];

        if (! empty($lineItems)) {
            $this->createLineItems($lineItems, $model);
        }
    }

    /**
     * Create line items for an invoice.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, InvoiceLineItem>
     */
    public function createLineItems(array $items, Invoice $invoice): array
    {
        $created = [];

        foreach ($items as $index => $item) {
            $created[] = InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'line_number' => $item['line_number'] ?? ($index + 1),
                'description' => $item['description'] ?? null,
                'sku' => $item['sku'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit_of_measure' => $item['unit_of_measure'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'discount_percent' => $item['discount_percent'] ?? null,
                'discount_amount' => $item['discount_amount'] ?? null,
                'tax_rate' => $item['tax_rate'] ?? null,
                'tax_amount' => $item['tax_amount'] ?? null,
                'total_amount' => $item['total_amount'] ?? null,
                'category' => $item['category'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return $created;
    }
}
