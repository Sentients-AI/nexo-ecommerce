<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type')->default('flat_rate');
            $table->unsignedInteger('rate_cents')->default(0);
            $table->unsignedInteger('min_order_cents')->nullable();
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_method_id')->nullable()->after('shipping_address')
                ->constrained('shipping_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeignIdFor(App\Domain\Shipping\Models\ShippingMethod::class);
            $table->dropColumn('shipping_method_id');
        });

        Schema::dropIfExists('shipping_methods');
    }
};
