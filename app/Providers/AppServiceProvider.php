<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Loyalty\Events\PointsEarned;
use App\Domain\Notification\Listeners\NotifyUserOnLoyaltyPointsEarned;
use App\Domain\Notification\Listeners\NotifyUserOnOrderStatusChange;
use App\Domain\Notification\Listeners\NotifyUserOnRefundApproved;
use App\Domain\Order\Events\OrderCancelled;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderRefunded;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Refund\Events\RefundApproved;
use App\Infrastructure\Payment\Stripe\PaymentGatewayService as StripePaymentGatewayService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
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
        $this->registerNotificationListeners();
    }

    /**
     * Register event listeners that dispatch in-app notifications.
     */
    private function registerNotificationListeners(): void
    {
        Event::listen([OrderPaid::class, OrderCancelled::class, OrderRefunded::class], NotifyUserOnOrderStatusChange::class);
        Event::listen(RefundApproved::class, NotifyUserOnRefundApproved::class);
        Event::listen(PointsEarned::class, NotifyUserOnLoyaltyPointsEarned::class);
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
