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
        Schema::create('alert_triggers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_definition_id')->constrained()->cascadeOnDelete();
            $table->decimal('actual_value', 20, 4);
            $table->decimal('threshold_value', 20, 4);
            $table->string('status')->default('active');
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['alert_definition_id', 'status']);
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_triggers');
    }
};
