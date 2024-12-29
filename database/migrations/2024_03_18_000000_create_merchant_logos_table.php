<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_logos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->binary('logo_data');  // For storing the actual image data
            $table->string('mime_type');  // For storing the image type (e.g., image/png, image/jpeg)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_logos');
    }
}; 