<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Infrastructure\Payment\Stripe\PaymentGatewayService as StripePaymentGatewayService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient(config('services.stripe.secret')));

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

        $this->configureTenantContext();
    }

    /**
     * Configure tenant context propagation for queue jobs.
     */
    private function configureTenantContext(): void
    {
        // Preserve tenant_id when serializing context for queued jobs.
        // The framework automatically restores the returned data via Context::hydrate().
        Context::dehydrating(fn ($context): array => [
            'tenant_id' => $context->get('tenant_id'),
        ]);
    }
}
