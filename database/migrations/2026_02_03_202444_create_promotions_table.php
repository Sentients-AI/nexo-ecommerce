<?php

declare(strict_types=1);

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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();

            // Discount configuration
            $table->string('discount_type'); // 'fixed' or 'percentage'
            $table->unsignedBigInteger('discount_value'); // cents for fixed, basis points for percentage (1000 = 10%)

            // Scope: 'all', 'product', 'category'
            $table->string('scope')->default('all');

            // Application mode
            $table->boolean('auto_apply')->default(false);

            // Time constraints
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // Constraints
            $table->unsignedBigInteger('minimum_order_cents')->nullable();
            $table->unsignedBigInteger('maximum_discount_cents')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'auto_apply', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
