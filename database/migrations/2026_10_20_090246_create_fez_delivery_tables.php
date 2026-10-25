<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_accounts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenantId')->unique();
            $table->string('provider')->default('FEZ');
            $table->string('environment')->default('sandbox');
            $table->string('baseUrl')->nullable();
            $table->text('userId');
            $table->text('password');
            $table->text('secretKey')->nullable();
            $table->text('authToken')->nullable();
            $table->timestamp('tokenExpiresAt')->nullable();
            $table->string('orgId')->nullable();
            $table->string('orgName')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('autoBook')->default(false);
            $table->string('pickupName')->nullable();
            $table->string('pickupPhone')->nullable();
            $table->text('pickupAddress')->nullable();
            $table->string('pickupState')->nullable();
            $table->decimal('defaultWeightKg', 8, 2)->default(1);
            $table->decimal('packagingWeightKg', 8, 2)->default(0);
            $table->string('status')->default('NOT_CONNECTED');
            $table->timestamp('lastTestedAt')->nullable();
            $table->text('lastError')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->index('tenantId');
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId')->unique();
            $table->string('deliveryAccountId')->nullable();
            $table->string('branchId')->nullable();
            $table->string('provider')->default('FEZ');
            $table->string('reference')->unique();
            $table->string('providerOrderNo')->nullable()->index();
            $table->string('status')->default('NOT_BOOKED');
            $table->string('providerStatus')->nullable();
            $table->string('paymentStatus')->nullable();
            $table->decimal('quotedFee', 12, 2)->default(0);
            $table->decimal('actualFee', 12, 2)->nullable();
            $table->decimal('weightKg', 8, 2)->default(1);
            $table->string('currency', 3)->default('NGN');
            $table->json('pickup')->nullable();
            $table->json('recipient')->nullable();
            $table->json('providerPayload')->nullable();
            $table->json('trackingHistory')->nullable();
            $table->text('proofOfDelivery')->nullable();
            $table->text('lastError')->nullable();
            $table->timestamp('bookedAt')->nullable();
            $table->timestamp('deliveredAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->index('tenantId');
            $table->index('status');
        });

        Schema::create('delivery_events', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('deliveryId');
            $table->string('status');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurredAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->index('deliveryId');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('deliveryMethod')->nullable()->after('channel');
            $table->string('deliveryState')->nullable()->after('customerAddress');
            $table->decimal('deliveryFee', 12, 2)->default(0)->after('serviceCharge');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('weightKg', 8, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('weightKg');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['deliveryMethod', 'deliveryState', 'deliveryFee']);
        });
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_accounts');
    }
};
