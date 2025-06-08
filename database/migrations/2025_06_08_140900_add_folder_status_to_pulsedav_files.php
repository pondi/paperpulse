<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing constraint
        DB::statement('ALTER TABLE pulsedav_files DROP CONSTRAINT IF EXISTS pulsedav_files_status_check');
        
        // Add the new constraint with 'folder' status
        DB::statement("ALTER TABLE pulsedav_files ADD CONSTRAINT pulsedav_files_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'folder'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the constraint
        DB::statement('ALTER TABLE pulsedav_files DROP CONSTRAINT IF EXISTS pulsedav_files_status_check');
        
        // Re-add the original constraint
        DB::statement("ALTER TABLE pulsedav_files ADD CONSTRAINT pulsedav_files_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
    }
};