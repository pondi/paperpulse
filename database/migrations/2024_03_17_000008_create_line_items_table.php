<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('text')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('qty', 8, 2)->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('total', 8, 2)->nullable();
            $table->timestamps();

            $table->index('receipt_id');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
