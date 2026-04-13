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
            $table->foreignId('experiment_id')->nullable()->after('is_flash_sale')
                ->constrained('promotion_experiments')->nullOnDelete();
            $table->string('variant', 1)->nullable()->after('experiment_id'); // 'A' or 'B'
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropForeign(['experiment_id']);
            $table->dropColumn(['experiment_id', 'variant']);
        });
    }
};
