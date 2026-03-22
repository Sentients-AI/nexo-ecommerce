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
        Schema::table('loyalty_transactions', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('balance_after');
            $table->index(['loyalty_account_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table): void {
            $table->dropIndex(['loyalty_account_id', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
