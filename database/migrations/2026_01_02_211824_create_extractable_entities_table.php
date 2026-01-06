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
        Schema::create('extractable_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Polymorphic fields
            $table->string('entity_type', 50); // 'receipt', 'voucher', 'invoice', 'contract', etc.
            $table->unsignedBigInteger('entity_id');

            // Extraction metadata
            $table->boolean('is_primary')->default(false);
            $table->decimal('confidence_score', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->string('extraction_provider', 50)->nullable(); // 'gemini', 'textract+openai'
            $table->string('extraction_model', 100)->nullable(); // 'gemini-2.0-flash', 'gpt-5.2'
            $table->json('extraction_metadata')->nullable(); // tokens used, processing time, etc.
            $table->timestamp('extracted_at');

            $table->timestamps();

            // Indexes
            $table->unique(['entity_type', 'entity_id']);
            $table->index(['file_id', 'entity_type'], 'idx_extractable_entities_file_entity_type');
            $table->index(['user_id', 'entity_type'], 'idx_extractable_entities_user_entity_type');
            $table->index('entity_type', 'idx_extractable_entities_entity_type');
            $table->index('is_primary', 'idx_extractable_entities_is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extractable_entities');
    }
};
