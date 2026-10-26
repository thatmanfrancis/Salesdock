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
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('cashlessOnly')->default(false);
            $table->boolean('requireVerifiedPayments')->default(false);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('paymentVerificationRequired')->default(false);
            $table->string('checkoutRequestId')->nullable();
            $table->unique(['tenantId', 'checkoutRequestId']);
            $table->index(['paymentVerificationRequired', 'status', 'updatedAt'], 'orders_payment_reconciliation_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tenantId', 'checkoutRequestId']);
            $table->dropIndex('orders_payment_reconciliation_index');
            $table->dropColumn(['paymentVerificationRequired', 'checkoutRequestId']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['cashlessOnly', 'requireVerifiedPayments']);
        });
    }
};
