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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('bundle_id')->nullable()->after('variant_id')->constrained()->nullOnDelete();
            $table->string('bundle_name_snapshot')->nullable()->after('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeignIdFor(App\Domain\Bundle\Models\Bundle::class);
            $table->dropColumn(['bundle_id', 'bundle_name_snapshot']);
        });
    }
};
