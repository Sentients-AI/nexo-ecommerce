<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path');
            $table->string('disk')->default('public');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index('review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_photos');
    }
};
