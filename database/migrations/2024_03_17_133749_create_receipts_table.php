<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files');
            $table->text('receipt_data')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('receipt_category')->nullable();
            $table->text('receipt_description')->nullable();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
