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
        Schema::create('pulsedav_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('imported_at')->useCurrent();
            $table->integer('file_count')->default(0);
            $table->json('tag_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'imported_at'], 'idx_user_imported');
        });
        
        // Add import_batch_id to pulsedav_files
        Schema::table('pulsedav_files', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->after('folder_tag_ids')
                ->constrained('pulsedav_import_batches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pulsedav_files', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropColumn('import_batch_id');
        });
        
        Schema::dropIfExists('pulsedav_import_batches');
    }
};