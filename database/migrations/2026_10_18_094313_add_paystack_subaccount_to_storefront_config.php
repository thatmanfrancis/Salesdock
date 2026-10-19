<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_config', function (Blueprint $table) {
            $table->string('paystackSubaccountCode')->nullable()->after('flutterwaveSubaccountId');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_config', function (Blueprint $table) {
            $table->dropColumn('paystackSubaccountCode');
        });
    }
};
