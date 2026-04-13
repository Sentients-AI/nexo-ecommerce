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
            $table->string('batch_id')->nullable()->index()->after('variant');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn('batch_id');
        });
    }
};
