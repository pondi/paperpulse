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
        Schema::table('pulsedav_files', function (Blueprint $table) {
            // Add folder support columns
            $table->string('folder_path', 500)->nullable()->after('filename')
                ->comment('Relative folder path within user directory');
            $table->string('parent_folder', 255)->nullable()->after('folder_path')
                ->comment('Direct parent folder name');
            $table->integer('depth')->default(0)->after('parent_folder')
                ->comment('Folder depth level');
            $table->boolean('is_folder')->default(false)->after('depth')
                ->comment('True if this is a folder entry');
            $table->json('folder_tag_ids')->nullable()->after('is_folder')
                ->comment('Tag IDs to apply to all files in folder');
            
            // Add indexes for performance
            $table->index(['user_id', 'folder_path'], 'idx_user_folder_path');
            $table->index(['user_id', 'parent_folder'], 'idx_user_parent_folder');
            $table->index('is_folder', 'idx_is_folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pulsedav_files', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_user_folder_path');
            $table->dropIndex('idx_user_parent_folder');
            $table->dropIndex('idx_is_folder');
            
            // Drop columns
            $table->dropColumn([
                'folder_path',
                'parent_folder',
                'depth',
                'is_folder',
                'folder_tag_ids'
            ]);
        });
    }
};