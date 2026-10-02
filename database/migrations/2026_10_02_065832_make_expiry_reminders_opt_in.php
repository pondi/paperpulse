<?php

use App\Models\UserPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The old in-app flags were enabled by schema defaults and had no settings controls.
     * Reset those implicit choices so scheduling starts only after an explicit opt-in.
     */
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->boolean('notify_voucher_expiring')->default(false)->change();
            $table->boolean('notify_warranty_expiring')->default(false)->change();
        });

        UserPreference::query()->update([
            'notify_voucher_expiring' => false,
            'notify_warranty_expiring' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->boolean('notify_voucher_expiring')->default(true)->change();
            $table->boolean('notify_warranty_expiring')->default(true)->change();
        });
    }
};
