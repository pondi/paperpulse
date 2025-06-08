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
        Schema::create('file_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id');
            $table->enum('file_type', ['receipt', 'document']);
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['file_id', 'file_type']);
            $table->index('tag_id');
            
            // Unique constraint to prevent duplicate tags
            $table->unique(['file_id', 'file_type', 'tag_id'], 'unique_file_tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_tags');
    }
};