<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Make user_id nullable for guest orders
            $table->foreignId('user_id')->nullable()->change();

            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_name')->nullable()->after('guest_email');
            $table->uuid('guest_token')->nullable()->unique()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['guest_email', 'guest_name', 'guest_token']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
