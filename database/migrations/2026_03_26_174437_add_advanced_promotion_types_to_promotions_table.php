<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            // BOGO: buy X get Y free
            $table->unsignedInteger('buy_quantity')->nullable()->after('per_user_limit');
            $table->unsignedInteger('get_quantity')->nullable()->after('buy_quantity');

            // Tiered: JSON array of [{min_cents, discount_bps}]
            $table->json('tiers')->nullable()->after('get_quantity');

            // Flash sale flag — shows countdown on frontend
            $table->boolean('is_flash_sale')->default(false)->after('tiers');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn(['buy_quantity', 'get_quantity', 'tiers', 'is_flash_sale']);
        });
    }
};
