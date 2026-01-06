<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('receipt_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');

            // Product info
            $table->string('product_name', 500)->nullable();
            $table->string('product_category')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();

            // Warranty details
            $table->date('purchase_date')->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->string('warranty_duration', 100)->nullable(); // "2 years", "24 months"

            $table->string('warranty_type', 50)->nullable(); // 'manufacturer', 'extended', 'store'
            $table->string('warranty_provider')->nullable();
            $table->string('warranty_number')->nullable();

            // Coverage
            $table->string('coverage_type', 100)->nullable(); // 'full', 'limited', 'parts_only'
            $table->text('coverage_description')->nullable();
            $table->text('exclusions')->nullable();

            // Contact
            $table->string('support_phone', 50)->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_website', 500)->nullable();

            $table->json('warranty_data')->nullable(); // Full extraction

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'warranty_end_date'], 'idx_warranties_user_end_date');
            $table->index('receipt_id', 'idx_warranties_receipt');
            $table->index('invoice_id', 'idx_warranties_invoice');
            $table->index('manufacturer', 'idx_warranties_manufacturer');
            $table->index('product_category', 'idx_warranties_product_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};
