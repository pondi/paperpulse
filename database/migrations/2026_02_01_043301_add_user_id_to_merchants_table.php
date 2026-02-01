<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Merchants were previously shared globally (privacy issue).
     * This migration makes them user-specific by:
     * 1. Adding user_id column
     * 2. Duplicating merchants for each user that references them
     * 3. Updating receipt references to point to user-specific merchants
     */
    public function up(): void
    {
        // Step 1: Add nullable user_id column
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Step 2: For each merchant, find all users who have receipts with this merchant
        // and create user-specific copies
        $merchants = DB::table('merchants')->whereNull('user_id')->get();

        foreach ($merchants as $merchant) {
            // Find all unique users who have receipts with this merchant
            $userIds = DB::table('receipts')
                ->where('merchant_id', $merchant->id)
                ->distinct()
                ->pluck('user_id');

            if ($userIds->isEmpty()) {
                // No receipts reference this merchant - delete it
                DB::table('merchants')->where('id', $merchant->id)->delete();

                continue;
            }

            // First user keeps the original merchant record
            $firstUserId = $userIds->shift();
            DB::table('merchants')
                ->where('id', $merchant->id)
                ->update(['user_id' => $firstUserId]);

            // Create copies for additional users
            foreach ($userIds as $userId) {
                $newMerchantId = DB::table('merchants')->insertGetId([
                    'user_id' => $userId,
                    'name' => $merchant->name,
                    'address' => $merchant->address,
                    'vat_number' => $merchant->vat_number,
                    'email' => $merchant->email,
                    'phone' => $merchant->phone,
                    'website' => $merchant->website,
                    'created_at' => $merchant->created_at,
                    'updated_at' => now(),
                ]);

                // Update this user's receipts to point to their copy
                DB::table('receipts')
                    ->where('merchant_id', $merchant->id)
                    ->where('user_id', $userId)
                    ->update(['merchant_id' => $newMerchantId]);
            }
        }

        // Step 3: Make user_id non-nullable
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Step 4: Add unique constraint on user_id + name
        Schema::table('merchants', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'merchants_user_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropUnique('merchants_user_id_name_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
