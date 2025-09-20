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
        Schema::table('files', function (Blueprint $table) {
            $table->string('s3_image_path')->nullable()->after('s3_processed_path');
            $table->boolean('has_image_preview')->default(false)->after('s3_image_path');
            $table->text('image_generation_error')->nullable()->after('has_image_preview');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['s3_image_path', 'has_image_preview', 'image_generation_error']);
        });
    }
};
