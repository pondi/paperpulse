<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('receipt_data')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('receipt_category')->nullable();
            $table->text('receipt_description')->nullable();
            $table->json('tags')->nullable();
            $table->json('shared_with')->nullable();
            $table->json('ai_entities')->nullable();
            $table->string('language')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'receipt_date']);
            $table->index(['merchant_id', 'receipt_date']);
            $table->index(['category_id', 'receipt_date']);
            $table->index('total_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
