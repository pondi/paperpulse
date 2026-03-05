<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Services\Receipt\ReceiptEnricherService;

class InvoiceFactory
{
    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    public function create(array $data, File $file): Invoice
    {
        $vendor = $data['vendor'] ?? [];
        $customer = $data['customer'] ?? [];
        $invoiceInfo = $data['invoice_info'] ?? [];
        $totals = $data['totals'] ?? [];
        $payment = $data['payment'] ?? [];
        $lineItems = $data['line_items'] ?? [];

        $invoice = Invoice::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'category_id' => $data['category_id'] ?? null,
            'invoice_number' => $invoiceInfo['invoice_number'] ?? $data['invoice_number'] ?? null,
            'invoice_type' => $invoiceInfo['invoice_type'] ?? $data['invoice_type'] ?? 'invoice',
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
            'subtotal' => $totals['subtotal'] ?? $data['subtotal'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? $data['tax_amount'] ?? 0,
            'discount_amount' => $totals['discount_amount'] ?? $data['discount_amount'] ?? 0,
            'shipping_amount' => $totals['shipping_amount'] ?? $data['shipping_amount'] ?? 0,
            'total_amount' => $totals['total_amount'] ?? $data['total_amount'] ?? 0,
            'amount_paid' => $totals['amount_paid'] ?? $data['amount_paid'] ?? 0,
            'amount_due' => $totals['amount_due'] ?? $data['amount_due'] ?? 0,
            'currency' => $payment['currency'] ?? $data['currency'] ?? 'NOK',
            'payment_method' => $payment['method'] ?? $data['payment_method'] ?? null,
            'payment_status' => $payment['status'] ?? $data['payment_status'] ?? null,
            'payment_terms' => $payment['terms'] ?? $data['payment_terms'] ?? null,
            'purchase_order_number' => $invoiceInfo['purchase_order_number'] ?? $data['purchase_order_number'] ?? null,
            'reference_number' => $invoiceInfo['reference_number'] ?? $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'invoice_data' => $data['invoice_data'] ?? $data,
        ]);

        if (! empty($lineItems)) {
            $this->createLineItems($lineItems, $invoice);
        }

        return $invoice;
    }

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

    protected function resolveMerchantId(array $data, File $file): ?int
    {
        $merchant = $data['merchant'] ?? [];

        if (empty($merchant) && ! empty($data['vendor'])) {
            $merchant = $data['vendor'];
        }

        if (empty($merchant) && isset($data['merchant_name'])) {
            $merchant = [
                'name' => $data['merchant_name'],
                'vat_number' => $data['merchant_vat'] ?? null,
                'address' => $data['merchant_address'] ?? null,
            ];
        }

        if (empty($merchant['name'])) {
            return null;
        }

        $merchantModel = $this->merchantEnricher->findOrCreateMerchant([
            'name' => $merchant['name'],
            'vat_number' => $merchant['vat_number'] ?? null,
            'address' => $merchant['address'] ?? null,
        ], $file->user_id);

        return $merchantModel?->id;
    }
}
