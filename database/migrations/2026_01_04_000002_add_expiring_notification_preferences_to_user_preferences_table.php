<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('notify_voucher_expiring')->default(true)->after('notify_weekly_summary_ready');
            $table->boolean('notify_warranty_expiring')->default(true)->after('notify_voucher_expiring');
            $table->boolean('email_notify_voucher_expiring')->default(false)->after('email_notify_weekly_summary');
            $table->boolean('email_notify_warranty_expiring')->default(false)->after('email_notify_voucher_expiring');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'notify_voucher_expiring',
                'notify_warranty_expiring',
                'email_notify_voucher_expiring',
                'email_notify_warranty_expiring',
            ]);
        });
    }
};
