<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Infrastructure\Payment\Stripe\PaymentGatewayService as StripePaymentGatewayService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayService::class, StripePaymentGatewayService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        Model::shouldBeStrict();
        Model::automaticallyEagerLoadRelationships();
    }
}
