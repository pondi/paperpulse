<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing foreign key constraint
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
        });

        // Add it back with cascade on delete
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreign('file_id')
                ->references('id')
                ->on('files')
                ->onDelete('cascade');
        });

        // Also fix file_tags table if it exists
        if (Schema::hasTable('file_tags')) {
            // Check if foreign key exists using raw query for PostgreSQL
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.table_constraints 
                WHERE constraint_type = 'FOREIGN KEY' 
                AND table_name = 'file_tags'
                AND constraint_name LIKE '%file_id%'
            ");

            if ($foreignKeyExists[0]->count > 0) {
                Schema::table('file_tags', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['file_id']);
                    } catch (\Exception $e) {
                        // Foreign key might not exist or have a different name
                    }
                });
            }

            // Add foreign key with cascade
            Schema::table('file_tags', function (Blueprint $table) {
                $table->foreign('file_id')
                    ->references('id')
                    ->on('files')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert receipts table foreign key
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')
                ->references('id')
                ->on('files');
        });

        // Revert file_tags table foreign key if it exists
        if (Schema::hasTable('file_tags')) {
            Schema::table('file_tags', function (Blueprint $table) {
                try {
                    $table->dropForeign(['file_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                $table->foreign('file_id')
                    ->references('id')
                    ->on('files');
            });
        }
    }
};