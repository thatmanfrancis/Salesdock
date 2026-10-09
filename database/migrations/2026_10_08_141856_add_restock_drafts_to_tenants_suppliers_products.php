<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('restockDrafts')->default(false);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('restockDrafts')->default(true);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('restockDraftedAt')->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('isAutomatic')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('isAutomatic');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('restockDraftedAt');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('restockDrafts');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('restockDrafts');
        });
    }
};
