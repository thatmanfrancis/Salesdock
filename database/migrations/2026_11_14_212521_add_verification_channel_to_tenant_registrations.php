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
        Schema::table('tenant_registrations', function (Blueprint $table) {
            $table->string('verificationChannel', 10)->nullable();
            $table->timestamp('phoneVerifiedAt')->nullable();
            $table->timestamp('verificationSentAt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_registrations', function (Blueprint $table) {
            $table->dropColumn(['verificationChannel', 'phoneVerifiedAt', 'verificationSentAt']);
        });
    }
};
