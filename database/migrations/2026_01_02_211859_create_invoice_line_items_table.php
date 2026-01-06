<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');

            $table->integer('line_number')->nullable();
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit_of_measure', 50)->nullable(); // 'pcs', 'hours', 'kg'
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();

            $table->string('category')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id', 'idx_invoice_line_items_invoice');
            $table->index(['invoice_id', 'line_number'], 'idx_invoice_line_items_line_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
