<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('receipt_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('merchant_id')->nullable()->constrained()->onDelete('set null');

            // Deadlines
            $table->date('return_deadline')->nullable();
            $table->date('exchange_deadline')->nullable();

            // Policy details
            $table->text('conditions')->nullable();
            $table->string('refund_method', 50)->nullable(); // 'full_refund', 'store_credit'
            $table->decimal('restocking_fee', 10, 2)->nullable();
            $table->decimal('restocking_fee_percentage', 5, 2)->nullable();

            // Status
            $table->boolean('is_final_sale')->default(false);
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('requires_original_packaging')->default(false);

            $table->json('policy_data')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'return_deadline'], 'idx_return_policies_user_deadline');
            $table->index('receipt_id', 'idx_return_policies_receipt');
            $table->index('invoice_id', 'idx_return_policies_invoice');
            $table->index('merchant_id', 'idx_return_policies_merchant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_policies');
    }
};
