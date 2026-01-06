<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('merchant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');

            // Invoice identification
            $table->string('invoice_number')->nullable();
            $table->string('invoice_type', 50)->nullable(); // 'invoice', 'credit_note', 'debit_note'

            // Parties
            $table->string('from_name', 500)->nullable();
            $table->text('from_address')->nullable();
            $table->string('from_vat_number', 100)->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_phone', 50)->nullable();

            $table->string('to_name', 500)->nullable();
            $table->text('to_address')->nullable();
            $table->string('to_vat_number', 100)->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_phone', 50)->nullable();

            // Dates
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();

            // Financial
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('shipping_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->string('currency', 3)->default('NOK');

            // Payment
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_status', 50)->nullable(); // 'paid', 'unpaid', 'partial', 'overdue'
            $table->text('payment_terms')->nullable();

            // References
            $table->string('purchase_order_number')->nullable();
            $table->string('reference_number')->nullable();

            // Additional
            $table->text('notes')->nullable();
            $table->json('invoice_data')->nullable(); // Full extraction

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'invoice_date'], 'idx_invoices_user_date');
            $table->index('merchant_id', 'idx_invoices_merchant');
            $table->index('invoice_number', 'idx_invoices_invoice_number');
            $table->index('due_date', 'idx_invoices_due_date');
            $table->index('payment_status', 'idx_invoices_payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
