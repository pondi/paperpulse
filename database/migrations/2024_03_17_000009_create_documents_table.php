<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->text('description')->nullable();
            $table->string('document_type')->default('other');
            $table->date('document_date')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->json('extracted_text')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_entities')->nullable();
            $table->string('language')->nullable();
            $table->integer('page_count')->default(1);
            $table->json('shared_with')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
            $table->index(['user_id', 'document_date']);
            $table->index(['category_id', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
