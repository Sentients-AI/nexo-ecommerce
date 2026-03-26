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
            $table->char('base_currency', 3)->default('MYR')->after('currency');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000)->after('base_currency');
            $table->unsignedBigInteger('base_total_cents')->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['base_currency', 'exchange_rate', 'base_total_cents']);
        });
    }
};
