<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Metrics\Contracts\MetricsDriver;
use App\Shared\Metrics\DatabaseMetricsDriver;
use Illuminate\Support\ServiceProvider;

final class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsDriver::class, DatabaseMetricsDriver::class);
    }
}
