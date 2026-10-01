<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->string('import_generation', 64)->nullable();
            $table->timestamp('categorized_at')->nullable();
            $table->unique(['file_id', 'import_generation']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropUnique(['file_id', 'import_generation']);
            $table->dropColumn(['import_generation', 'categorized_at']);
        });
    }
};
