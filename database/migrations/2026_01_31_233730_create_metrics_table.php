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
        Schema::create('metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('counter');
            $table->decimal('value', 20, 4)->default(0);
            $table->json('labels')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['name', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
