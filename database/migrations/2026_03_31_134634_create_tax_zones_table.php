<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->char('country_code', 2)->nullable()->comment('ISO 3166-1 alpha-2; null = global fallback');
            $table->string('region_code', 10)->nullable()->comment('State/province code for sub-national zones');
            $table->decimal('rate', 5, 4)->comment('Fractional rate e.g. 0.1000 = 10%');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Most specific match wins: prefer region > country > global
            $table->index(['tenant_id', 'country_code', 'region_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_zones');
    }
};
