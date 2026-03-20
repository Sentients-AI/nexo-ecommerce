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
        Schema::create('referral_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('referee_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('referrer_points_awarded');
            $table->unsignedTinyInteger('referee_discount_percent');
            $table->string('referee_coupon_code')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'referral_code_id', 'referee_user_id'], 'referral_usages_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_usages');
    }
};
