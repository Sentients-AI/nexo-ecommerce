# Comprehensive Codebase Guide

This document explains every aspect of the modular e-commerce system, including the purpose of each file, design patterns, trade-offs, and implementation details.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Technology Stack](#technology-stack)
3. [Directory Structure](#directory-structure)
4. [Domain Layer](#domain-layer)
5. [Application Layer](#application-layer)
6. [HTTP Layer](#http-layer)
7. [Infrastructure Layer](#infrastructure-layer)
8. [Filament Admin Panel](#filament-admin-panel)
9. [Frontend (Vue/Inertia)](#frontend-vueinertia)
10. [Database Design](#database-design)
11. [Design Patterns](#design-patterns)
12. [Testing Strategy](#testing-strategy)
13. [Configuration](#configuration)
14. [Trade-offs and Decisions](#trade-offs-and-decisions)

---

## Architecture Overview

### What Is This Application?

This is a **modular monolith e-commerce system** built with Laravel, implementing Domain-Driven Design (DDD) principles. It handles:

- Product catalog and categories
- Shopping cart management
- Order processing and checkout
- Payment processing (Stripe)
- Inventory management with concurrency control
- Refund workflow with approval process
- Promotions and discounts
- Admin control panel (Filament)

### Request Flow

```
Browser Request
      ↓
┌─────────────────────────────────────┐
│         HTTP LAYER                   │
│  Routes → Middleware → Controllers  │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│       APPLICATION LAYER              │
│         Use Cases                    │
│   (Orchestration & Coordination)    │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│         DOMAIN LAYER                 │
│  Models, Actions, Specifications,   │
│  Events, Guards, Value Objects      │
└─────────────────────────────────────┘
      ↓
┌─────────────────────────────────────┐
│      INFRASTRUCTURE LAYER            │
│   Database, External APIs (Stripe)  │
└─────────────────────────────────────┘
      ↓
HTTP Response (JSON or Inertia Page)
```

---

## Technology Stack

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4.1 | Server-side language |
| Laravel | 12.44.0 | PHP framework |
| Sanctum | 4.2.1 | API authentication |
| Cashier | 16.1.0 | Stripe subscription billing utilities |

### Admin Panel
| Technology | Version | Purpose |
|------------|---------|---------|
| Filament | 5.0.0 | Admin dashboard framework |
| Livewire | 4.0.1 | Dynamic UI components |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| Vue.js | 3.5.27 | Reactive UI framework |
| Inertia.js | 2.3.13 | SPA without API |
| Tailwind CSS | 4.1.18 | Utility-first CSS |

### Testing
| Technology | Version | Purpose |
|------------|---------|---------|
| Pest | 4.3.0 | Testing framework |
| PHPUnit | 12.5.4 | Test runner |

### Quality Tools
| Technology | Version | Purpose |
|------------|---------|---------|
| Pint | 1.26.0 | Code style fixer |
| Rector | 2.3.1 | Automated refactoring |

---

## Directory Structure

```
app/
├── Application/          # Use cases (orchestration layer)
│   ├── DTOs/            # Request/Response data transfer objects
│   └── UseCases/        # Business operation orchestrators
│
├── Console/             # Artisan commands
│   └── Commands/        # Custom CLI commands
│
├── Domain/              # Core business logic (DDD)
│   ├── Cart/           # Shopping cart bounded context
│   ├── Category/       # Product categories
│   ├── Config/         # System configuration
│   ├── FeatureFlag/    # Feature toggles
│   ├── Idempotency/    # Duplicate request prevention
│   ├── Inventory/      # Stock management
│   ├── Order/          # Order processing
│   ├── Payment/        # Payment handling
│   ├── Product/        # Product catalog
│   ├── Projections/    # Read models (CQRS-lite)
│   ├── Promotion/      # Discounts and promotions
│   ├── Refund/         # Refund workflow
│   ├── Role/           # User roles
│   ├── Shared/         # Cross-cutting domain concerns
│   ├── Tax/            # Tax calculation
│   └── User/           # User management
│
├── Filament/            # Admin panel
│   ├── Pages/          # Custom admin pages
│   ├── Resources/      # CRUD resources
│   └── Widgets/        # Dashboard widgets
│
├── Http/                # HTTP handling
│   ├── Controllers/    # Request handlers
│   ├── Middleware/     # Request/response processors
│   ├── Requests/       # Form validation
│   ├── Resources/      # API response formatters
│   └── Responses/      # Custom response classes
│
├── Infrastructure/      # External service integrations
│   └── Payment/        # Stripe gateway
│
├── Policies/            # Authorization policies
│
├── Providers/           # Service providers
│
└── Shared/              # Application-wide utilities
    ├── Alerting/       # System health alerts
    ├── Metrics/        # Performance tracking
    └── Services/       # Shared services

resources/
├── js/                  # Frontend JavaScript
│   ├── Components/     # Vue components
│   ├── Composables/    # Vue composition API hooks
│   ├── Layouts/        # Page layouts
│   ├── Pages/          # Inertia page components
│   └── types/          # TypeScript definitions
│
└── views/               # Blade templates (minimal)

database/
├── factories/           # Model factories for testing
├── migrations/          # Database schema
└── seeders/            # Test data seeders

tests/
├── Feature/            # Integration tests
│   ├── Api/           # API endpoint tests
│   ├── Browser/       # Browser tests (Pest 4)
│   ├── Domains/       # Domain logic tests
│   └── Torture/       # Stress/concurrency tests
│
└── Unit/               # Unit tests
```

---

## Domain Layer

The domain layer is the heart of the application. Each domain is a "bounded context" - a self-contained area of business logic.

### Domain Structure Pattern

Each domain follows this structure:

```
Domain/
└── {DomainName}/
    ├── Actions/          # Single-responsibility operations
    ├── DTOs/             # Data transfer objects
    ├── Enums/            # Domain-specific enumerations
    ├── Events/           # Domain events
    ├── Exceptions/       # Domain-specific exceptions
    ├── Guards/           # State transition validators
    ├── Listeners/        # Event handlers
    ├── Models/           # Eloquent models (aggregates)
    ├── Policies/         # Authorization rules
    ├── Specifications/   # Business rule validators
    └── ValueObjects/     # Type-safe identifiers
```

---

### Cart Domain

**Purpose**: Manage shopping cart lifecycle - adding items, updating quantities, clearing cart.

#### Models

**Cart.php**
```php
final class Cart extends Model
{
    // Relationships
    public function user(): BelongsTo;      // Owner of the cart
    public function items(): HasMany;        // Cart items

    // Business methods
    public function getSubtotalAttribute(): int;  // Calculate total in cents
    public function isEmpty(): bool;              // Check if cart has items
}
```

**Why `final`?** Prevents inheritance, making the model's behavior predictable. All business logic changes happen through Actions, not subclasses.

**CartItem.php**
```php
final class CartItem extends Model
{
    // IMPORTANT: We snapshot the price when item is added
    protected $fillable = [
        'product_id',
        'quantity',
        'price_cents_snapshot',  // Price at time of adding
        'tax_cents_snapshot',    // Tax at time of adding
    ];
}
```

**Why snapshot prices?** If product prices change after a customer adds to cart, they should still see the price they expected. This prevents surprise price changes at checkout.

#### Actions

**AddItemToCartAction.php**
```php
final readonly class AddItemToCartAction
{
    public function execute(AddItemToCartData $data): CartItem
    {
        // 1. Find or create cart for user
        // 2. Check if product already in cart
        // 3. If exists, update quantity; otherwise, create new item
        // 4. Snapshot current price
    }
}
```

**Why Actions instead of methods on Model?**
- Single Responsibility: Each action does one thing
- Testable: Easy to mock dependencies
- Explicit Dependencies: Constructor shows what the action needs
- No God Objects: Models stay focused on data, not behavior

#### Specifications

**CartHasItems.php**
```php
final class CartHasItems extends Specification
{
    public function isSatisfiedBy(mixed $cart): bool
    {
        return $cart->items()->exists();
    }

    public function getFailureReason(): string
    {
        return 'Cart is empty. Add items before checkout.';
    }
}
```

**Why Specifications?**
- Named Rules: `CartHasItems` is clearer than `if ($cart->items->count() > 0)`
- Composable: Can combine with `and()`, `or()`, `not()`
- Reusable: Same rule used in checkout, cart display, etc.
- Testable: Business rule isolated for unit testing

**Trade-off**: More files, but each file has one clear purpose.

---

### Inventory Domain

**Purpose**: Track stock levels with concurrency-safe operations.

#### Models

**Stock.php**
```php
final class Stock extends Model
{
    protected $fillable = [
        'product_id',
        'quantity_available',  // Stock ready to sell
        'quantity_reserved',   // Stock held for pending orders
    ];

    // Critical: SELECT FOR UPDATE to prevent race conditions
    public function scopeForUpdate(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }
}
```

**Why two quantity fields?**
```
quantity_available = 10  (can be sold)
quantity_reserved = 3    (held for pending checkouts)
---
physical_stock = 13      (actual items in warehouse)
```

When customer starts checkout, we "reserve" stock. If payment fails, we "release" it back. This prevents overselling.

**StockMovement.php**
```php
final class StockMovement extends Model
{
    // Every stock change is recorded
    protected $fillable = [
        'stock_id',
        'type',           // IN, OUT, RESERVE, RELEASE, ADJUSTMENT
        'quantity',       // Positive or negative
        'reference_type', // Order, Refund, Manual adjustment
        'reference_id',
        'notes',
    ];
}
```

**Why record every movement?** Audit trail. If stock numbers look wrong, we can trace exactly what happened and when.

#### Actions

**ReserveStockAction.php**
```php
final readonly class ReserveStockAction
{
    public function execute(ReserveStockData $data): void
    {
        DB::transaction(function () use ($data) {
            // CRITICAL: Lock the stock row to prevent race conditions
            $stock = Stock::query()
                ->where('product_id', $data->productId)
                ->lockForUpdate()  // SELECT ... FOR UPDATE
                ->firstOrFail();

            // Check availability
            if ($stock->quantity_available < $data->quantity) {
                throw new InsufficientStockException();
            }

            // Reserve the stock
            $stock->quantity_available -= $data->quantity;
            $stock->quantity_reserved += $data->quantity;
            $stock->save();

            // Record the movement for audit
            StockMovement::create([
                'stock_id' => $stock->id,
                'type' => StockMovementType::Reserve,
                'quantity' => -$data->quantity,
                'reference_type' => Order::class,
                'reference_id' => $data->orderId,
            ]);
        });
    }
}
```

**Why `lockForUpdate()`?**

Without locking, two concurrent checkouts could both see "10 available" and both reserve, resulting in -2 available (oversold).

With `lockForUpdate()`:
1. Request A reads stock (locks row)
2. Request B tries to read, waits for lock
3. Request A reserves, commits, releases lock
4. Request B now reads updated value

**Trade-off**: Slight performance cost for database locks, but prevents inventory disasters.

---

### Order Domain

**Purpose**: Create orders from carts, manage order lifecycle, publish domain events.

#### Models

**Order.php**
```php
final class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal_cents',
        'tax_cents',
        'shipping_cents',
        'discount_cents',
        'total_cents',
        'promotion_id',
    ];

    // Status is an enum for type safety
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }
}
```

**OrderItem.php**
```php
final class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',      // Snapshot - product might be renamed
        'product_sku',       // Snapshot - SKU might change
        'quantity',
        'unit_price_cents',  // Snapshot - price at purchase time
        'total_cents',
    ];
}
```

**Why snapshot product data?** An order is a historical record. If someone orders "Blue Widget" for $10, then we rename it to "Azure Widget" and change price to $15, the order should still show "Blue Widget, $10".

#### Enums

**OrderStatus.php**
```php
enum OrderStatus: string
{
    case Pending = 'pending';       // Order created, awaiting payment
    case Paid = 'paid';             // Payment received
    case Shipped = 'shipped';       // Order shipped to customer
    case Completed = 'completed';   // Customer received order
    case Cancelled = 'cancelled';   // Order cancelled

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($newStatus, [self::Paid, self::Cancelled]),
            self::Paid => in_array($newStatus, [self::Shipped, self::Cancelled]),
            self::Shipped => $newStatus === self::Completed,
            self::Completed, self::Cancelled => false, // Terminal states
        };
    }
}
```

**Why enums instead of strings?**
- Type Safety: Can't accidentally set status to "paied" (typo)
- IDE Support: Autocomplete and refactoring
- Single Source of Truth: All valid statuses in one place
- Behavior: Can add methods like `canTransitionTo()`

#### Guards

**OrderGuards.php**
```php
final class OrderGuards
{
    public static function canBePaid(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidOrderStateException(
                "Cannot pay order in {$order->status->value} status"
            );
        }
    }

    public static function canBeCancelled(Order $order): void
    {
        if (!in_array($order->status, [OrderStatus::Pending, OrderStatus::Paid])) {
            throw new InvalidOrderStateException(
                "Cannot cancel order in {$order->status->value} status"
            );
        }
    }
}
```

**Why Guards?**
- Explicit Rules: Clear what transitions are allowed
- Single Location: All state rules in one file
- Fail Fast: Throws exception immediately on invalid transition
- Self-Documenting: Reading guards shows valid order lifecycle

#### Events

**OrderCreated.php**
```php
final readonly class OrderCreated extends DomainEvent
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public int $totalCents,
        public string $orderNumber,
    ) {}
}
```

**Why Domain Events?**
- Decoupling: Order domain doesn't need to know about inventory, email, etc.
- Audit Trail: Events can be stored and replayed
- Eventual Consistency: Other systems react to events asynchronously
- Testing: Can verify correct events were published

**Event Flow Example**:
```
Order Created
    → StockReserved (Inventory listens)
    → SendOrderConfirmationEmail (Email listens)
    → UpdateOrderProjection (Analytics listens)
```

#### Actions

**CreateOrderFromCartAction.php**
```php
final readonly class CreateOrderFromCartAction
{
    public function __construct(
        private ReserveStockAction $reserveStock,
        private CalculateDiscountAction $calculateDiscount,
        private CalculateTaxAction $calculateTax,
    ) {}

    public function execute(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 1. Validate cart has items
            (new CartHasItems())->assertSatisfiedBy($data->cart);

            // 2. Calculate promotion discount if applicable
            $discount = $this->calculateDiscount->execute(...);

            // 3. Calculate subtotal
            $subtotal = $this->calculateSubtotal($data->cart);

            // 4. Calculate tax on discounted amount
            $tax = $this->calculateTax->execute($subtotal - $discount);

            // 5. Create order with all amounts
            $order = Order::create([
                'user_id' => $data->userId,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'tax_cents' => $tax,
                'total_cents' => $subtotal - $discount + $tax,
            ]);

            // 6. Copy cart items to order items (with snapshots)
            foreach ($data->cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,  // Snapshot
                    'product_sku' => $item->product->sku,    // Snapshot
                    'unit_price_cents' => $item->price_cents_snapshot,
                    'quantity' => $item->quantity,
                    'total_cents' => $item->price_cents_snapshot * $item->quantity,
                ]);

                // 7. Reserve stock for each item
                $this->reserveStock->execute(new ReserveStockData(
                    productId: $item->product_id,
                    quantity: $item->quantity,
                    orderId: $order->id,
                ));
            }

            // 8. Dispatch domain event
            event(new OrderCreated(
                orderId: $order->id,
                userId: $data->userId,
                totalCents: $order->total_cents,
                orderNumber: $order->order_number,
            ));

            return $order;
        });
    }
}
```

**Why wrap in `DB::transaction()`?** If stock reservation fails for the third item, the entire order (including items 1 and 2) is rolled back. No partial orders.

---

### Payment Domain

**Purpose**: Handle payment processing with Stripe integration.

#### Models

**PaymentIntent.php**
```php
final class PaymentIntent extends Model
{
    protected $fillable = [
        'order_id',
        'provider',              // 'stripe'
        'provider_reference',    // Stripe's payment intent ID
        'client_secret',         // For frontend confirmation
        'amount_cents',
        'currency',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'provider' => PaymentProvider::class,
        ];
    }
}
```

#### Services

**PaymentGatewayService.php** (Infrastructure Layer)
```php
final readonly class PaymentGatewayService implements PaymentGatewayContract
{
    public function createIntent(CreatePaymentIntentDTO $data): ProviderResponse
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount' => $data->amountCents,
                'currency' => strtolower($data->currency),
                'metadata' => [
                    'order_id' => $data->orderId,
                    'order_number' => $data->orderNumber,
                ],
            ]);

            return new ProviderResponse(
                provider: 'stripe',
                reference: $intent->id,
                clientSecret: $intent->client_secret,
            );
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException($e->getMessage());
        }
    }
}
```

**Why an interface (contract)?**
```php
interface PaymentGatewayContract
{
    public function createIntent(CreatePaymentIntentDTO $data): ProviderResponse;
    public function confirmIntent(string $reference): ProviderResponse;
}
```

- Testability: Can mock payment gateway in tests
- Flexibility: Could add PayPal, Square, etc. without changing domain code
- Dependency Inversion: Domain depends on abstraction, not Stripe directly

#### Webhooks

**HandlePaymentWebhookAction.php**
```php
final readonly class HandlePaymentWebhookAction
{
    public function execute(array $payload, string $signature): void
    {
        // 1. Verify webhook signature (security)
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );

        // 2. Handle based on event type
        match ($event->type) {
            'payment_intent.succeeded' => $this->handleSuccess($event),
            'payment_intent.payment_failed' => $this->handleFailure($event),
            default => null, // Ignore other events
        };
    }

    private function handleSuccess(Event $event): void
    {
        $paymentIntent = PaymentIntent::query()
            ->where('provider_reference', $event->data->object->id)
            ->firstOrFail();

        // Update status
        $paymentIntent->update(['status' => PaymentStatus::Succeeded]);

        // Mark order as paid
        $this->markOrderAsPaid->execute($paymentIntent->order_id);

        // Dispatch event
        event(new PaymentIntentSucceeded(...));
    }
}
```

**Why webhooks instead of just client-side confirmation?**
- Reliability: Network issues might prevent client callback
- Security: Webhook signature proves it's from Stripe
- Reconciliation: Can handle payments even if user closes browser

---

### Refund Domain

**Purpose**: Handle refund requests with approval workflow.

#### Models

**Refund.php**
```php
final class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'amount_cents',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'processed_at',
        'stripe_refund_id',
    ];
}
```

#### Workflow

```
Customer requests refund → RefundRequested event
         ↓
Admin reviews and approves → RefundApproved event
         ↓
System processes via Stripe → RefundSucceeded/RefundFailed event
         ↓
If failed, stock is restored → StockReleased event
```

#### Specifications

**RefundAmountIsValid.php**
```php
final class RefundAmountIsValid extends Specification
{
    public function __construct(
        private Order $order,
        private int $requestedAmount,
    ) {}

    public function isSatisfiedBy(mixed $subject): bool
    {
        // Cannot refund more than paid
        $alreadyRefunded = $this->order->refunds()
            ->whereIn('status', [RefundStatus::Approved, RefundStatus::Processed])
            ->sum('amount_cents');

        $remainingRefundable = $this->order->total_cents - $alreadyRefunded;

        return $this->requestedAmount <= $remainingRefundable;
    }

    public function getFailureReason(): string
    {
        return 'Refund amount exceeds remaining refundable amount.';
    }
}
```

---

### Promotion Domain

**Purpose**: Handle discount codes and automatic promotions.

#### Models

**Promotion.php**
```php
final class Promotion extends Model
{
    protected $fillable = [
        'name',
        'code',               // Nullable - auto-apply if null
        'discount_type',      // 'fixed' or 'percentage'
        'discount_value',     // Cents for fixed, basis points for percentage
        'scope',              // 'all', 'product', 'category'
        'auto_apply',         // Automatically apply best promotion
        'starts_at',
        'ends_at',
        'minimum_order_cents',
        'maximum_discount_cents',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'is_active',
    ];

    // Relationships for scoped promotions
    public function products(): BelongsToMany;
    public function categories(): BelongsToMany;
}
```

**Why basis points for percentage?**
```php
// 1000 basis points = 10%
// Avoids floating point issues
$discount = ($subtotal * $promotion->discount_value) / 10000;
```

#### Actions

**FindBestPromotionAction.php**
```php
final readonly class FindBestPromotionAction
{
    public function execute(Cart $cart, User $user, ?string $code = null): ?array
    {
        // If code provided, validate that specific promotion
        if ($code !== null) {
            return $this->findByCode($cart, $user, $code);
        }

        // Otherwise, find best auto-apply promotion
        return $this->findBestAutoApply($cart, $user);
    }

    private function findBestAutoApply(Cart $cart, User $user): ?array
    {
        // Get all valid auto-apply promotions
        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where('auto_apply', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get();

        // Calculate discount for each, return the best
        $bestDiscount = 0;
        $bestPromotion = null;

        foreach ($promotions as $promotion) {
            // Skip if user exceeded per-user limit
            if (!$this->userCanUse($promotion, $user)) continue;

            // Skip if cart doesn't meet minimum
            if (!$this->cartMeetsMinimum($promotion, $cart)) continue;

            $discount = $this->calculateDiscount->execute($cart, $promotion);

            if ($discount->discountCents > $bestDiscount) {
                $bestDiscount = $discount->discountCents;
                $bestPromotion = $promotion;
            }
        }

        return $bestPromotion ? ['promotion' => $bestPromotion, 'result' => $discount] : null;
    }
}
```

---

### Idempotency Domain

**Purpose**: Prevent duplicate operations from network retries or double-clicks.

#### How It Works

```
Request comes in with header: Idempotency-Key: abc123
         ↓
Check if we've seen abc123 before
         ↓
If yes: Return the cached response (don't process again)
If no: Process request, cache response, return response
```

#### Models

**IdempotencyKey.php**
```php
final class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',              // Client-provided key
        'user_id',
        'operation',        // 'checkout', 'refund', etc.
        'fingerprint',      // Hash of request payload
        'status_code',      // Cached response status
        'response',         // Cached response body
        'expires_at',
    ];
}
```

#### Actions

**EnsureIdempotentAction.php**
```php
final readonly class EnsureIdempotentAction
{
    public function execute(
        string $key,
        int $userId,
        string $operation,
        array $payload,
    ): ?array {
        $fingerprint = RequestFingerprint::generate($operation, $payload);

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('user_id', $userId)
            ->where('operation', $operation)
            ->first();

        if ($existing === null) {
            return null; // Not seen before, proceed with request
        }

        // Verify same payload (detect misuse)
        if ($existing->fingerprint !== $fingerprint) {
            throw new IdempotencyKeyConflictException(
                'Same idempotency key used with different payload'
            );
        }

        // Return cached response
        return json_decode($existing->response, true);
    }
}
```

**Why fingerprint?** If someone sends same key with different data, that's a bug (or attack). We catch it early.

---

### Projections Domain

**Purpose**: Maintain denormalized read models for fast queries and analytics.

#### Why Projections?

**Without projections** (calculating on-the-fly):
```sql
SELECT
    orders.id,
    orders.total_cents,
    SUM(refunds.amount_cents) as refunded,
    (orders.total_cents - COALESCE(SUM(refunds.amount_cents), 0)) as net
FROM orders
LEFT JOIN refunds ON ...
WHERE ...
GROUP BY orders.id
```

Problems:
- Slow with large datasets
- Complex query repeated everywhere
- Calculation logic duplicated

**With projections** (pre-calculated):
```sql
SELECT * FROM order_financial_projections WHERE order_id = 123
```

Benefits:
- Fast (single row lookup)
- Calculation done once (when events occur)
- Easy to query

#### Models

**OrderFinancialProjection.php**
```php
final class OrderFinancialProjection extends Model
{
    protected $fillable = [
        'order_id',
        'total_amount',
        'paid_amount',
        'refunded_amount',
        'net_amount',
        'refund_status',  // 'none', 'partial', 'full'
    ];
}
```

#### Event Handlers

```php
class UpdateOrderFinancialsOnRefund
{
    public function handle(RefundSucceeded $event): void
    {
        $projection = OrderFinancialProjection::findOrFail($event->orderId);

        $projection->refunded_amount += $event->amountCents;
        $projection->net_amount = $projection->total_amount - $projection->refunded_amount;
        $projection->refund_status = $this->calculateStatus($projection);
        $projection->save();
    }
}
```

**Trade-off**: Data is eventually consistent. Between event and listener, projection might be stale. For most analytics, this is acceptable.

---

## Application Layer

The application layer orchestrates complex operations that span multiple domains.

### Use Cases

**CheckoutUseCase.php**
```php
final readonly class CheckoutUseCase
{
    public function __construct(
        private CreateOrderFromCartAction $createOrder,
        private CreatePaymentIntentAction $createPayment,
        private FindBestPromotionAction $findPromotion,
        private RecordPromotionUsageAction $recordUsage,
    ) {}

    public function execute(CheckoutRequest $request): CheckoutResponse
    {
        return DB::transaction(function () use ($request) {
            // 1. Find best applicable promotion
            $promotionResult = $this->findPromotion->execute(
                $request->cart,
                $request->user,
                $request->promotionCode,
            );

            // 2. Create order from cart with promotion
            $order = $this->createOrder->execute(new CreateOrderData(
                cart: $request->cart,
                userId: $request->user->id,
                promotion: $promotionResult?['promotion'],
                discountCents: $promotionResult?['result']->discountCents ?? 0,
            ));

            // 3. Record promotion usage
            if ($promotionResult) {
                $this->recordUsage->execute(
                    $promotionResult['promotion'],
                    $request->user,
                    $order,
                    $promotionResult['result']->discountCents,
                );
            }

            // 4. Create payment intent
            $paymentIntent = $this->createPayment->execute(new CreatePaymentIntentData(
                orderId: $order->id,
                amountCents: $order->total_cents,
                currency: $order->currency,
            ));

            // 5. Clear the cart
            $request->cart->items()->delete();

            return new CheckoutResponse(
                order: $order,
                clientSecret: $paymentIntent->client_secret,
            );
        });
    }
}
```

**Why a Use Case class instead of putting this in Controller?**
- Testable: Can test checkout logic without HTTP
- Reusable: Same logic for API and web
- Single Responsibility: Controller handles HTTP, UseCase handles business logic
- Clear Dependencies: All required services in constructor

---

## HTTP Layer

### Controllers

Controllers are thin - they validate input, call use cases, and return responses.

**CheckoutController.php** (API)
```php
final class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutUseCase $checkout,
        private EnsureIdempotentAction $ensureIdempotent,
        private StoreIdempotencyResultAction $storeIdempotency,
    ) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        // Check for cached response (duplicate request)
        $cached = $this->ensureIdempotent->execute(
            $idempotencyKey,
            $request->user()->id,
            'checkout',
            $request->validated(),
        );

        if ($cached !== null) {
            return response()->json($cached);
        }

        // Process checkout
        $response = $this->checkout->execute(
            CheckoutRequest::fromHttpRequest($request)
        );

        // Cache response for idempotency
        $this->storeIdempotency->execute(
            $idempotencyKey,
            $request->user()->id,
            'checkout',
            $request->validated(),
            200,
            $response->toArray(),
        );

        return response()->json($response->toArray());
    }
}
```

### Middleware

**TransformDomainExceptions.php**
```php
final class TransformDomainExceptions
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => $e->getMessage(),
                    'retryable' => false,
                ],
            ], 422);
        } catch (InvalidOrderStateException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_ORDER_STATE',
                    'message' => $e->getMessage(),
                    'retryable' => false,
                ],
            ], 409);
        }
        // ... more exception types
    }
}
```

**Why this middleware?**
- Domain exceptions become proper HTTP responses
- Consistent error format across all endpoints
- Domain layer doesn't need to know about HTTP

### Form Requests

**CheckoutRequest.php**
```php
final class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'promotion_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'promotion_code.max' => 'Promotion code is too long.',
        ];
    }
}
```

### API Resources

**OrderResource.php**
```php
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Why Resources?**
- Consistent response format
- Hide internal fields (e.g., `updated_at`, `deleted_at`)
- Transform data (e.g., format dates)
- Conditional loading (`whenLoaded`)

---

## Infrastructure Layer

External service integrations isolated from business logic.

### Stripe Integration

**PaymentGatewayService.php**
```php
final readonly class PaymentGatewayService implements PaymentGatewayContract
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function createIntent(CreatePaymentIntentDTO $data): ProviderResponse
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $data->amountCents,
            'currency' => strtolower($data->currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => $data->orderId,
            ],
        ]);

        return new ProviderResponse(
            provider: 'stripe',
            reference: $intent->id,
            clientSecret: $intent->client_secret,
        );
    }
}
```

**Service Provider binding:**
```php
// AppServiceProvider.php
$this->app->bind(
    PaymentGatewayContract::class,
    PaymentGatewayService::class
);
```

---

## Filament Admin Panel

### Resource Structure

Each Filament resource has:

```
Resources/
└── Orders/
    ├── OrderResource.php           # Main resource class
    ├── Pages/
    │   ├── ListOrders.php         # Index page
    │   ├── CreateOrder.php        # Create page
    │   └── EditOrder.php          # Edit page
    ├── Schemas/
    │   ├── OrderForm.php          # Form field definitions
    │   └── OrderInfolist.php      # View-only display
    ├── Tables/
    │   └── OrdersTable.php        # Table columns and filters
    └── RelationManagers/
        └── OrderItemsRelationManager.php  # Nested items table
```

### Example Resource

**OrderResource.php**
```php
final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form
    {
        return OrderForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
```

### Widgets

**RevenueTodayWidget.php**
```php
final class RevenueTodayWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', OrderStatus::Cancelled)
            ->sum('total_cents');

        return [
            Stat::make('Today\'s Revenue', '$' . number_format($today / 100, 2))
                ->description('From ' . Order::whereDate('created_at', today())->count() . ' orders')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
        ];
    }
}
```

---

## Frontend (Vue/Inertia)

### Page Components

Inertia pages receive data from Laravel controllers as props.

**Products/Index.vue**
```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type { ProductApiResource, CategoryApiResource } from '@/types/api';

interface Props {
    products: LaravelPaginator<ProductApiResource>;
    categories: CategoryApiResource[];
    filters: {
        search: string | null;
        category: string | null;
    };
}

const props = defineProps<Props>();

// Filter state
const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');

function applyFilters() {
    router.get('/products', {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
    }, {
        preserveState: true,  // Keep component state
        preserveScroll: true, // Don't scroll to top
    });
}
</script>
```

### Composables

Reusable logic with Vue Composition API.

**useCart.ts**
```typescript
import { ref, computed } from 'vue';
import axios from 'axios';

// Shared state across all components
const cart = ref<CartApiResource | null>(null);
const loading = ref(false);
const error = ref<ApiError | null>(null);

export function useCart() {
    async function addToCart(productId: number, quantity: number) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.post('/api/v1/cart/items', {
                product_id: productId,
                quantity,
            });
            cart.value = data.data.cart;
            return true;
        } catch (e) {
            error.value = e.response?.data?.error;
            return false;
        } finally {
            loading.value = false;
        }
    }

    return {
        cart: computed(() => cart.value),
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        addToCart,
    };
}
```

**Why composables?**
- Shared State: Cart state shared across all components
- Reusable Logic: Add to cart from product card, product page, etc.
- Reactive: UI updates automatically when cart changes
- Testable: Can mock for component tests

### Type Definitions

**types/api.ts**
```typescript
export interface ProductApiResource {
    id: number;
    sku: string;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    sale_price: number | null;
    currency: string;
    is_active: boolean;
    is_featured: boolean;
    images: string[];
    category?: CategoryApiResource;
    stock?: {
        quantity: number;
        available: number;
    };
}
```

**Why TypeScript?**
- Catches errors at compile time
- IDE autocomplete and refactoring
- Self-documenting code
- Matches Laravel API Resources

---

## Database Design

### Key Tables

**orders**
```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    order_number VARCHAR(20) UNIQUE,
    status ENUM('pending', 'paid', 'shipped', 'completed', 'cancelled'),
    subtotal_cents BIGINT,
    discount_cents BIGINT DEFAULT 0,
    tax_cents BIGINT,
    shipping_cents BIGINT DEFAULT 0,
    total_cents BIGINT,
    promotion_id BIGINT REFERENCES promotions(id) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**order_items**
```sql
CREATE TABLE order_items (
    id BIGINT PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id),
    product_name VARCHAR(255),      -- Snapshot
    product_sku VARCHAR(50),        -- Snapshot
    quantity INT,
    unit_price_cents BIGINT,        -- Snapshot
    total_cents BIGINT,
    created_at TIMESTAMP
);
```

**stocks**
```sql
CREATE TABLE stocks (
    id BIGINT PRIMARY KEY,
    product_id BIGINT UNIQUE REFERENCES products(id),
    quantity_available INT,
    quantity_reserved INT,
    updated_at TIMESTAMP
);
```

**stock_movements**
```sql
CREATE TABLE stock_movements (
    id BIGINT PRIMARY KEY,
    stock_id BIGINT REFERENCES stocks(id),
    type ENUM('in', 'out', 'reserve', 'release', 'adjustment'),
    quantity INT,
    reference_type VARCHAR(255),    -- 'Order', 'Refund', etc.
    reference_id BIGINT,
    notes TEXT NULL,
    created_at TIMESTAMP
);
```

### Design Decisions

1. **Money in cents**: Avoids floating-point precision issues
2. **Snapshots in order_items**: Historical accuracy
3. **Separate reserved/available**: Support for pending checkouts
4. **Stock movements table**: Full audit trail
5. **Soft deletes avoided**: Hard delete with audit log instead

---

## Design Patterns

### Specification Pattern

```php
// Define business rules as composable objects
$canCheckout = (new CartHasItems())
    ->and(new CartIsNotCompleted())
    ->and(new UserHasVerifiedEmail());

// Use them
if (!$canCheckout->isSatisfiedBy($cart)) {
    throw new CheckoutException($canCheckout->getFailureReason());
}

// Or with assertion
$canCheckout->assertSatisfiedBy($cart); // Throws if not satisfied
```

**Implementation:**
```php
abstract class Specification
{
    abstract public function isSatisfiedBy(mixed $subject): bool;
    abstract public function getFailureReason(): string;

    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): Specification
    {
        return new NotSpecification($this);
    }

    public function assertSatisfiedBy(mixed $subject): void
    {
        if (!$this->isSatisfiedBy($subject)) {
            throw new SpecificationNotSatisfiedException($this->getFailureReason());
        }
    }
}
```

### Value Objects

```php
// Type-safe IDs prevent mixing up order IDs with user IDs
final readonly class OrderId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

// Usage
function processOrder(OrderId $orderId): void { ... }

// This won't compile (type error):
processOrder(UserId::fromInt(123));
```

### Guard Clauses

```php
final class OrderGuards
{
    public static function canBePaid(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidOrderStateException(
                "Cannot mark order as paid: current status is {$order->status->value}"
            );
        }
    }
}

// Usage in action
public function execute(int $orderId): void
{
    $order = Order::findOrFail($orderId);

    OrderGuards::canBePaid($order);  // Throws if invalid

    $order->update(['status' => OrderStatus::Paid]);
}
```

---

## Testing Strategy

### Test Types

| Type | Location | Purpose |
|------|----------|---------|
| Unit | `tests/Unit/` | Test single classes in isolation |
| Feature | `tests/Feature/` | Test features end-to-end |
| Browser | `tests/Feature/Browser/` | Test UI with real browser |
| Torture | `tests/Feature/Torture/` | Stress and concurrency tests |

### Unit Test Example

```php
// tests/Unit/Domain/Order/OrderStatusTest.php
it('allows pending to transition to paid', function () {
    $status = OrderStatus::Pending;

    expect($status->canTransitionTo(OrderStatus::Paid))->toBeTrue();
});

it('prevents completed from transitioning to any status', function () {
    $status = OrderStatus::Completed;

    expect($status->canTransitionTo(OrderStatus::Paid))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});
```

### Feature Test Example

```php
// tests/Feature/Api/V1/CheckoutApiTest.php
it('creates order and payment intent on checkout', function () {
    // Arrange
    $user = User::factory()->create();
    $product = Product::factory()->create(['price_cents' => 1000]);
    Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10]);

    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $cart->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price_cents_snapshot' => 1000,
    ]);

    // Mock payment gateway
    $this->mock(PaymentGatewayContract::class)
        ->shouldReceive('createIntent')
        ->andReturn(new ProviderResponse('stripe', 'pi_123', 'secret_123'));

    // Act
    $response = $this->actingAs($user)
        ->postJson('/api/v1/checkout', [], [
            'Idempotency-Key' => 'test-key-123',
        ]);

    // Assert
    $response->assertSuccessful();
    $response->assertJsonPath('data.order.total_cents', 2000);
    $response->assertJsonPath('data.client_secret', 'secret_123');

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'total_cents' => 2000,
        'status' => 'pending',
    ]);
});
```

### Torture Test Example

```php
// tests/Feature/Torture/ConcurrentStockReservationTest.php
it('handles concurrent stock reservations without overselling', function () {
    $product = Product::factory()->create();
    Stock::factory()->create([
        'product_id' => $product->id,
        'quantity_available' => 5,
    ]);

    // Create 10 users trying to reserve 1 item each
    $users = User::factory()->count(10)->create();

    $results = collect($users)->map(function ($user) use ($product) {
        return Process::run([
            'php', 'artisan', 'test:reserve-stock',
            '--product=' . $product->id,
            '--user=' . $user->id,
        ]);
    });

    // Only 5 should succeed
    $successes = $results->filter(fn ($r) => $r->successful())->count();
    expect($successes)->toBe(5);

    // Stock should never go negative
    $stock = Stock::where('product_id', $product->id)->first();
    expect($stock->quantity_available)->toBeGreaterThanOrEqual(0);
});
```

---

## Configuration

### Key Configuration Files

**config/essentials.php**
```php
return [
    'currency' => env('APP_CURRENCY', 'USD'),
    'payment_provider' => env('PAYMENT_PROVIDER', 'stripe'),
    'order_number_prefix' => env('ORDER_NUMBER_PREFIX', 'ORD'),
];
```

**config/services.php**
```php
return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
```

### Environment Variables

```env
# Application
APP_NAME="E-Commerce"
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=secret

# Payment
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

---

## Trade-offs and Decisions

### 1. Modular Monolith vs Microservices

**Chose**: Modular Monolith

**Why**:
- Single deployment = simpler operations
- In-process calls = no network latency
- Database transactions = atomic operations
- Team size doesn't justify microservices overhead

**Trade-off**: Must maintain domain boundaries through discipline (code review, architecture tests), not infrastructure.

**When to reconsider**: If one domain needs independent scaling (e.g., inventory checks spike during flash sales).

### 2. Actions vs Service Classes

**Chose**: Single-purpose Action classes

**Why**:
- Each class has one reason to change
- Dependencies explicit in constructor
- Easy to test in isolation
- Easy to find (action name = business operation)

**Trade-off**: More files. A "service" might become 5-10 action classes.

**Alternative**: Service classes with methods. Works but tends toward God Objects over time.

### 3. Specifications vs Inline Validation

**Chose**: Specification pattern

**Why**:
- Named rules (self-documenting)
- Composable (`and()`, `or()`, `not()`)
- Reusable across contexts
- Unit testable

**Trade-off**: More complex than `if` statements. Learning curve for new developers.

**When to use inline**: Truly simple, one-off checks that won't be reused.

### 4. Event-Driven vs Direct Calls

**Chose**: Domain events for cross-domain communication

**Why**:
- Domains stay decoupled
- Can add new listeners without changing publisher
- Audit trail of what happened
- Eventual consistency acceptable for most operations

**Trade-off**: Harder to trace execution flow. Events processed asynchronously.

**When to use direct calls**: Within same domain or when immediate consistency required.

### 5. Projections vs Calculated on Read

**Chose**: Projections for analytics/reporting

**Why**:
- Fast reads (pre-calculated)
- Complex calculations done once
- Historical snapshots possible

**Trade-off**: Data staleness between event and projection update. Must handle projection drift.

**When to calculate on read**: Simple queries, infrequently accessed data, need real-time accuracy.

### 6. Money in Cents vs Decimal

**Chose**: Store money as integers (cents)

**Why**:
- No floating-point precision issues
- `1000` cents is unambiguous
- Math operations are exact

**Trade-off**: Must convert for display (`$10.00` = `1000` cents).

### 7. SELECT FOR UPDATE vs Optimistic Locking

**Chose**: `SELECT FOR UPDATE` for inventory

**Why**:
- Guarantees no overselling
- Simple mental model
- Works with MySQL/PostgreSQL

**Trade-off**: Slight performance hit from row locks. Can cause deadlocks with poor transaction design.

**Alternative**: Optimistic locking (version column). Better for low-contention scenarios.

### 8. Filament vs Custom Admin

**Chose**: Filament

**Why**:
- Rapid development
- Built-in auth, tables, forms
- Extensible when needed
- Good dark mode support

**Trade-off**: Tied to Filament's upgrade cycle. Some customizations harder than custom build.

### 9. Vue/Inertia vs Blade/Livewire

**Chose**: Vue 3 + Inertia.js

**Why**:
- Rich interactive UI
- TypeScript support
- Large ecosystem
- Team familiarity

**Trade-off**: Build step required. Larger client-side bundle.

**Alternative**: Livewire for simpler interactivity. Used in admin panel via Filament.

### 10. Idempotency Keys vs Database Constraints

**Chose**: Explicit idempotency key system

**Why**:
- Works for complex operations (not just inserts)
- Returns cached response (not just prevents duplicate)
- Explicit expiration

**Trade-off**: Additional storage. Must pass key from client.

**Alternative**: Unique constraints work for simple cases (`user_id + product_id` on cart items).

---

## Summary

This codebase demonstrates a production-ready e-commerce system with:

1. **Clear Architecture**: Domain-driven design with bounded contexts
2. **Type Safety**: Enums, value objects, TypeScript
3. **Business Rules**: Specifications pattern for composable validation
4. **Concurrency Control**: SELECT FOR UPDATE for inventory
5. **Event-Driven**: Domain events for cross-cutting concerns
6. **Audit Trail**: Stock movements, domain events, audit logs
7. **Idempotency**: Prevents duplicate operations
8. **Comprehensive Testing**: Unit, feature, browser, torture tests
9. **Modern Frontend**: Vue 3, Inertia, Tailwind CSS
10. **Admin Dashboard**: Filament with custom widgets and pages

The architecture prioritizes maintainability and correctness over raw performance, making it suitable for most e-commerce volumes while remaining extractable to microservices if needed.
