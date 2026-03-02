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
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('category_group')->nullable()->after('category');
            $table->string('subcategory')->nullable()->after('category_group');
            $table->index('category_group', 'idx_bank_transactions_category_group');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_bank_transactions_category_group');
            $table->dropColumn(['category_group', 'subcategory']);
        });
    }
};
