<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->timestamp('file_created_at')->nullable()->after('uploaded_at');
            $table->timestamp('file_modified_at')->nullable()->after('file_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['file_created_at', 'file_modified_at']);
        });
    }
};
