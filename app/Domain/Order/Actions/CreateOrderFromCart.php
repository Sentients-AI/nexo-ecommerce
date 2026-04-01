<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Cart\Exceptions\EmptyCartException;
use App\Domain\Cart\Models\Cart;
use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Inventory\Actions\ReserveStock;
use App\Domain\Inventory\DTOs\ReserveStockData;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderCreated;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Actions\CalculateShippingCostAction;
use App\Domain\Tax\Actions\CalculateTax;
use App\Domain\Tax\DTOs\TaxCalculationData;
use App\Events\OrderStatusUpdated;
use App\Shared\Metrics\MetricsRecorder;
use Exception;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class CreateOrderFromCart
{
    public function __construct(
        private ReserveStock $reserveStock,
        private CalculateTax $calculateTax,
        private CurrencyService $currencyService,
        private CalculateShippingCostAction $calculateShipping,
    ) {}

    /**
     * Execute the action to create an order from a cart.
     *
     * INVARIANT: Stock reservation must be validated BEFORE order creation
     * to prevent split-brain scenarios where order exists without reserved stock.
     *
     * @throws Exception
     */
    public function execute(CreateOrderData $data): Order
    {
        try {
            $order = $this->createOrder($data);
            MetricsRecorder::increment('orders_created_total', ['currency' => $data->currency]);

            return $order;
        } catch (InsufficientStockException $e) {
            MetricsRecorder::increment('orders_checkout_failed_total', ['reason' => 'insufficient_stock']);
            throw $e;
        } catch (EmptyCartException $e) {
            MetricsRecorder::increment('orders_checkout_failed_total', ['reason' => 'empty_cart']);
            throw $e;
        } catch (Throwable $e) {
            MetricsRecorder::increment('orders_checkout_failed_total', ['reason' => 'unknown']);
            throw $e;
        }
    }

    private function createOrder(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            $cart = Cart::query()
                ->with('items.product')
                ->findOrFail($data->cartId);

            // Guard: Cannot create order from an already completed cart
            // Must check BEFORE isEmpty() since completed carts have items deleted
            $cart->assertNotCompleted();

            if ($cart->isEmpty()) {
                throw new EmptyCartException('Cannot create order from empty cart');
            }

            // STEP 1: Lock and validate ALL stock BEFORE creating anything
            // This prevents creating an order when stock is insufficient
            $stockRecords = $this->lockAndValidateStock($cart);

            // Calculate totals
            $subtotalCents = $cart->subtotal;
            $discountCents = $data->discountCents;

            // Apply promotion discount (cannot exceed subtotal)
            $discountCents = min($discountCents, (int) $subtotalCents);

            // Apply loyalty discount on top of promotion discount
            $loyaltyDiscountCents = min($data->loyaltyDiscountCents, max(0, (int) $subtotalCents - $discountCents));

            $subtotalAfterDiscount = $subtotalCents - $discountCents - $loyaltyDiscountCents;

            $shippingCountry = $data->shippingAddress['country'] ?? $data->shippingAddress['country_code'] ?? null;
            $shippingRegion = $data->shippingAddress['state'] ?? $data->shippingAddress['region_code'] ?? null;

            $taxCents = $this->calculateTax->execute(
                new TaxCalculationData(
                    subtotalCents: (int) $subtotalAfterDiscount,
                    countryCode: is_string($shippingCountry) ? mb_strtoupper($shippingCountry) : null,
                    regionCode: is_string($shippingRegion) ? mb_strtoupper($shippingRegion) : null,
                )
            );

            $shippingCents = $this->calculateShipping->execute(
                $data->shippingMethodId,
                (int) $subtotalAfterDiscount,
            );

            $totalCents = $subtotalAfterDiscount + $taxCents + $shippingCents;

            // Resolve tenant base currency and apply conversion if needed
            $tenant = Context::get('tenant');
            $baseCurrency = $tenant?->getSetting('currency', config('tenancy.default_settings.currency', 'MYR')) ?? 'MYR';
            $checkoutCurrency = mb_strtoupper($data->currency);

            $exchangeRate = $this->currencyService->getRate($baseCurrency, $checkoutCurrency);
            $convertedTotalCents = $this->currencyService->convertCents((int) $totalCents, $baseCurrency, $checkoutCurrency);
            $convertedSubtotalCents = $this->currencyService->convertCents((int) $subtotalCents, $baseCurrency, $checkoutCurrency);
            $convertedTaxCents = $this->currencyService->convertCents($taxCents, $baseCurrency, $checkoutCurrency);
            $convertedShippingCents = $this->currencyService->convertCents($shippingCents, $baseCurrency, $checkoutCurrency);
            $convertedDiscountCents = $this->currencyService->convertCents($discountCents, $baseCurrency, $checkoutCurrency);
            $convertedLoyaltyDiscountCents = $this->currencyService->convertCents($loyaltyDiscountCents, $baseCurrency, $checkoutCurrency);

            // STEP 2: Create order AFTER stock validation passes
            $guestToken = $data->userId === null ? (string) Str::uuid() : null;

            $order = Order::query()->create([
                'user_id' => $data->userId,
                'guest_email' => $data->guestEmail,
                'guest_name' => $data->guestName,
                'guest_token' => $guestToken,
                'order_number' => Order::generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'subtotal_cents' => $convertedSubtotalCents,
                'tax_cents' => $convertedTaxCents,
                'shipping_cost_cents' => $convertedShippingCents,
                'total_cents' => $convertedTotalCents,
                'currency' => $checkoutCurrency,
                'base_currency' => $baseCurrency,
                'exchange_rate' => $exchangeRate,
                'base_total_cents' => (int) $totalCents,
                'promotion_id' => $data->promotionId,
                'discount_cents' => $convertedDiscountCents,
                'loyalty_discount_cents' => $convertedLoyaltyDiscountCents,
                'shipping_method_id' => $data->shippingMethodId,
            ]);

            // STEP 3: Create order items and reserve stock (already validated)
            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'price_cents_snapshot' => $cartItem->price_cents_snapshot,
                    'tax_cents_snapshot' => $cartItem->tax_cents_snapshot,
                    'quantity' => $cartItem->quantity,
                ]);

                // Reserve stock - we already validated availability
                $this->reserveStock->execute(new ReserveStockData(
                    productId: $cartItem->product_id,
                    quantity: $cartItem->quantity,
                    variantId: $cartItem->variant_id,
                    orderId: $order->id,
                ));
            }

            // STEP 4: Archive the cart (mark as completed)
            $cart->markAsCompleted();

            $freshOrder = $order->fresh(['items']);

            OrderCreated::dispatch(
                $freshOrder->id,
                $freshOrder->user_id,
                $freshOrder->total_cents,
                $freshOrder->currency,
            );

            OrderStatusUpdated::dispatch(
                $freshOrder->id,
                $freshOrder->user_id,
                $freshOrder->tenant_id,
                $freshOrder->order_number,
                $freshOrder->status->value,
            );

            return $freshOrder;
        });
    }

    /**
     * Lock and validate all stock records for cart items.
     * Throws InsufficientStockException if any item has insufficient stock.
     *
     * @return array<int, Stock>
     */
    private function lockAndValidateStock(Cart $cart): array
    {
        $stockRecords = [];

        foreach ($cart->items as $cartItem) {
            $stock = Stock::query()
                ->where('product_id', $cartItem->product_id)
                ->when($cartItem->variant_id !== null, fn ($q) => $q->where('variant_id', $cartItem->variant_id))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $stock->isAvailable($cartItem->quantity)) {
                throw new InsufficientStockException(
                    productId: $cartItem->product_id,
                    requested: $cartItem->quantity,
                    available: $stock->quantity_available
                );
            }

            $key = $cartItem->variant_id !== null
                ? "{$cartItem->product_id}_{$cartItem->variant_id}"
                : (string) $cartItem->product_id;
            $stockRecords[$key] = $stock;
        }

        return $stockRecords;
    }
}
