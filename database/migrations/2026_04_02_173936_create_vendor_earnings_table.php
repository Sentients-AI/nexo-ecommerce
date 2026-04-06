<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->integer('gross_amount_cents');
            $table->integer('platform_fee_cents')->default(0);
            $table->integer('net_amount_cents');
            $table->integer('refunded_amount_cents')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_earnings');
    }
};
