<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\MetricsServiceProvider;
use Barryvdh\DomPDF\ServiceProvider as DomPDFServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MetricsServiceProvider::class,
    DomPDFServiceProvider::class,
];
