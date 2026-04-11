<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('tenant_id', Context::get('tenant_id'))
                    ->orWhereNull('tenant_id');
            })
            ->orderBy('price_cents')
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'billing_interval' => $plan->billing_interval->value,
                'interval_label' => $plan->intervalLabel(),
                'price_cents' => $plan->price_cents,
                'formatted_price' => $plan->formattedPrice(),
                'features' => $plan->features ?? [],
                'stripe_price_id' => $plan->stripe_price_id,
            ]);

        $activeSubscriptions = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->get()
            ->map(fn ($sub): array => [
                'id' => $sub->id,
                'stripe_price' => $sub->stripe_price,
                'stripe_status' => $sub->stripe_status,
                'ends_at' => $sub->ends_at?->toDateString(),
                'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
                'on_grace_period' => $sub->onGracePeriod(),
                'cancelled' => $sub->canceled(),
            ]);

        return Inertia::render('Subscriptions/Index', [
            'plans' => $plans,
            'subscriptions' => $activeSubscriptions,
            'has_payment_method' => $user->hasDefaultPaymentMethod(),
        ]);
    }

    public function checkout(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $locale = $request->route('locale', 'en');
        $user = $request->user();

        $checkout = $user->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => url("/{$locale}/subscriptions?subscribed=1"),
                'cancel_url' => url("/{$locale}/subscriptions"),
                'metadata' => [
                    'plan_id' => $plan->id,
                    'tenant_id' => Context::get('tenant_id'),
                ],
            ]);

        return redirect($checkout->url);
    }

    public function portal(Request $request): RedirectResponse
    {
        $locale = $request->route('locale', 'en');

        return $request->user()->redirectToBillingPortal(
            url("/{$locale}/subscriptions")
        );
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        $subscription = $user->subscription('default');

        if ($subscription && $subscription->active()) {
            $subscription->cancel();
        }

        return back()->with('success', 'Subscription cancelled. You retain access until the end of your billing period.');
    }
}
