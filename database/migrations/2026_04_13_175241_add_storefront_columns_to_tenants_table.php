<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('description');
            $table->string('banner_path')->nullable()->after('logo_path');
            $table->string('accent_color', 7)->nullable()->after('banner_path');
            $table->json('social_links')->nullable()->after('accent_color');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'banner_path', 'accent_color', 'social_links']);
        });
    }
};
