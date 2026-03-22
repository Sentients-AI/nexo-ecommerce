<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original unique('user_id') constraint only allowed one cart row per user ever,
     * which broke cart creation after a cart was marked completed (completed_at set).
     * Removing this allows users to have one active cart (completed_at IS NULL) plus
     * historical completed carts.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            // Add a plain index first so MySQL doesn't complain about the FK losing its index
            $table->index('user_id', 'carts_user_id_index');
            $table->dropUnique('carts_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->unique('user_id');
            $table->dropIndex('carts_user_id_index');
        });
    }
};
