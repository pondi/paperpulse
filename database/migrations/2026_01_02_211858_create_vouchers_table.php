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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('merchant_id')->nullable()->constrained()->onDelete('set null');

            // Voucher identification
            $table->string('voucher_type', 50); // 'gift_card', 'payment_plan', 'store_credit', 'coupon', 'promo_code'
            $table->string('code', 500)->nullable(); // Voucher code
            $table->string('barcode', 500)->nullable(); // Barcode data
            $table->text('qr_code')->nullable(); // QR code data

            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Financial
            $table->decimal('original_value', 12, 2)->nullable();
            $table->decimal('current_value', 12, 2)->nullable();
            $table->string('currency', 3)->default('NOK');

            // Payment plan specific (tilgodelapp)
            $table->integer('installment_count')->nullable();
            $table->decimal('monthly_payment', 12, 2)->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('final_payment_date')->nullable();

            // Status
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redemption_location')->nullable();

            // Additional info
            $table->text('terms_and_conditions')->nullable();
            $table->text('restrictions')->nullable();
            $table->json('voucher_data')->nullable(); // Full extraction

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'expiry_date'], 'idx_vouchers_user_expiry');
            $table->index('merchant_id', 'idx_vouchers_merchant');
            $table->index('voucher_type', 'idx_vouchers_voucher_type');
            $table->index('is_redeemed', 'idx_vouchers_is_redeemed');
            $table->index('code', 'idx_vouchers_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
