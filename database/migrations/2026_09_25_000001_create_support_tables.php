<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('userId');
            $table->string('subject', 255);
            $table->text('message');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])->default('OPEN');
            $table->enum('priority', ['LOW', 'NORMAL', 'HIGH', 'URGENT'])->default('NORMAL');
            $table->string('claimedBy')->nullable();
            $table->timestamp('resolvedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['tenantId']);
            $table->index(['userId']);
            $table->index(['status']);
            $table->index(['createdAt']);
        });

        Schema::create('support_replies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('ticketId');
            $table->string('userId');
            $table->boolean('isAdmin')->default(false);
            $table->text('message');
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['ticketId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_replies');
        Schema::dropIfExists('support_tickets');
    }
};
