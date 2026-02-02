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
        Schema::create('alert_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description');
            $table->string('metric_name');
            $table->string('condition');
            $table->decimal('threshold', 20, 4);
            $table->unsignedInteger('window_minutes')->default(5);
            $table->string('severity')->default('warning');
            $table->boolean('is_active')->default(true);
            $table->json('labels')->nullable();
            $table->json('notification_channels')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'metric_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_definitions');
    }
};
