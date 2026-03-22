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
        Schema::table('referral_usages', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->after('referee_coupon_code')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_usages', function (Blueprint $table): void {
            $table->dropForeignIdFor(App\Domain\Promotion\Models\Promotion::class);
            $table->dropColumn('promotion_id');
        });
    }
};
