<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable();
            $table->string('normalized_name')->nullable();
            $table->string('identity_key', 64)->nullable();
            $table->string('folder_type', 30)->default('folder');
            $table->string('organization_source', 20)->default('manual');
            $table->boolean('is_pinned')->default(false);
            $table->unique(['id', 'user_id']);
            $table->foreign(['parent_id', 'user_id'])->references(['id', 'user_id'])->on('collections')->restrictOnDelete();
            $table->unique(['user_id', 'identity_key']);
        });
        DB::table('collections')->orderBy('id')->chunkById(200, function ($collections): void {
            foreach ($collections as $collection) {
                DB::table('collections')->where('id', $collection->id)->update([
                    'normalized_name' => mb_strtolower(preg_replace('/\s+/u', ' ', trim($collection->name))),
                    'identity_key' => DB::table('collections')->where('user_id', $collection->user_id)
                        ->where('identity_key', hash('sha256', '0|folder|'.mb_strtolower(preg_replace('/\s+/u', ' ', trim($collection->name)))))->exists()
                        ? hash('sha256', 'legacy:'.$collection->id)
                        : hash('sha256', '0|folder|'.mb_strtolower(preg_replace('/\s+/u', ' ', trim($collection->name)))),
                ]);
            }
        });
        Schema::table('collections', function (Blueprint $table): void {
            $table->string('normalized_name')->nullable(false)->change();
            $table->string('identity_key', 64)->nullable(false)->change();
        });
        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('primary_folder_id')->nullable();
            $table->string('placement_source', 20)->nullable();
            $table->unsignedBigInteger('placement_version')->default(0);
            $table->foreign(['primary_folder_id', 'user_id'])->references(['id', 'user_id'])->on('collections')->restrictOnDelete();
        });
        Schema::table('collection_file', function (Blueprint $table): void {
            $table->boolean('is_primary_placement')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('collection_file', fn (Blueprint $table) => $table->dropColumn('is_primary_placement'));
        Schema::table('files', function (Blueprint $table): void {
            $table->dropForeign(['primary_folder_id', 'user_id']);
            $table->dropColumn(['primary_folder_id', 'placement_source', 'placement_version']);
        });
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropForeign(['parent_id', 'user_id']);
            $table->dropUnique(['user_id', 'identity_key']);
            $table->dropUnique(['id', 'user_id']);
            $table->dropColumn(['parent_id', 'normalized_name', 'identity_key', 'folder_type', 'organization_source', 'is_pinned']);
        });
    }
};
