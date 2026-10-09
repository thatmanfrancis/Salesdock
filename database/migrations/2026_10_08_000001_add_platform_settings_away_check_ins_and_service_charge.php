<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('away_check_ins', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId')->nullable();
            $table->string('userId');
            $table->unsignedInteger('daysAway');
            $table->timestamp('lastSeenAt')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['tenantId']);
            $table->index(['userId']);
            $table->index(['createdAt']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('serviceCharge', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('serviceCharge');
        });
        Schema::dropIfExists('away_check_ins');
        Schema::dropIfExists('platform_settings');
    }
};
