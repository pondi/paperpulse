<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collection_id', 'file_id']);
            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_file');
    }
};
