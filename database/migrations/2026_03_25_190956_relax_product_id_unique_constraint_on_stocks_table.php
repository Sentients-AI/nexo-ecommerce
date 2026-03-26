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
        Schema::table('stocks', function (Blueprint $table) {
            // Drop FK first (MySQL requires this before dropping the unique index it uses)
            $table->dropForeign(['product_id']);
            $table->dropUnique(['product_id']);

            // Make product_id nullable for variant-level stock records
            $table->unsignedBigInteger('product_id')->nullable()->change();

            // Re-add FK without the unique constraint
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Each variant may only have one stock record
            $table->unique('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique(['variant_id']);
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique('product_id');
        });
    }
};
