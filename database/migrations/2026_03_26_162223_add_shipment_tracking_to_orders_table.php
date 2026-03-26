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
            $table->string('carrier')->nullable()->after('shipping_address');
            $table->string('tracking_number')->nullable()->after('carrier');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->date('estimated_delivery_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['carrier', 'tracking_number', 'shipped_at', 'estimated_delivery_at']);
        });
    }
};
