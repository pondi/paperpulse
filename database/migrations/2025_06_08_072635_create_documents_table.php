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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content')->nullable();
            $table->enum('document_type', ['invoice', 'contract', 'letter', 'report', 'memo', 'other'])->default('other');
            $table->date('document_date')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('ai_summary')->nullable();
            $table->json('ai_entities')->nullable();
            $table->string('language', 10)->nullable();
            $table->integer('page_count')->default(1);
            $table->json('shared_with')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('document_type');
            $table->index('document_date');
            $table->index('language');
            $table->index('category_id');
            $table->index(['user_id', 'document_type']);
            $table->index(['user_id', 'document_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};