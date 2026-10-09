<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres cannot use a new enum value inside the transaction that adds it.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TYPE "ExpenseCategory" ADD VALUE IF NOT EXISTS \'INGREDIENTS\'');
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('paymentMethod')->nullable();
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('expenseId');
            $table->string('name');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('amount', 14, 2);
            $table->integer('position')->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->nullable();
            $table->foreign('expenseId')->references('id')->on('expenses')->cascadeOnDelete();
            $table->index(['tenantId', 'name']);
            $table->index(['expenseId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('paymentMethod');
        });
    }
};
