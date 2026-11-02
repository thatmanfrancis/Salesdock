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
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('autoRenew')->default(false);
            $table->string('recurringProvider')->nullable();
            $table->text('recurringAuthorization')->nullable();
            $table->json('recurringConsent')->nullable();
            $table->decimal('recurringAmount', 10, 2)->nullable();
            $table->timestamp('recurringRemindedAt')->nullable();
            $table->timestamp('autoRenewCancelledAt')->nullable();
            $table->index(['autoRenew', 'currentPeriodEnd']);
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->json('billingMetadata')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropColumn('billingMetadata');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['autoRenew', 'currentPeriodEnd']);
            $table->dropColumn(['autoRenew', 'recurringProvider', 'recurringAuthorization', 'recurringConsent', 'recurringAmount', 'recurringRemindedAt', 'autoRenewCancelledAt']);
        });
    }
};
