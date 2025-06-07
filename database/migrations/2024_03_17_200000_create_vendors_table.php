<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Legg til vendor_id i line_items tabellen
        Schema::table('line_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
        Schema::dropIfExists('vendors');
    }
};
