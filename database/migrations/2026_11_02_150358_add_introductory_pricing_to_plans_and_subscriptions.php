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
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('introductoryPricing')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->json('billingPricing')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('billingPricing');
        });
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('introductoryPricing');
        });
    }
};
