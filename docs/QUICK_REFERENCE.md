# Quick Reference Guide

Fast lookup for common tasks and code locations.

---

## Where to Find Things

### Business Logic

| What | Where | Example |
|------|-------|---------|
| Create order | `app/Domain/Order/Actions/CreateOrderFromCartAction.php` | `$action->execute($data)` |
| Reserve stock | `app/Domain/Inventory/Actions/ReserveStockAction.php` | `$action->execute($data)` |
| Process payment | `app/Domain/Payment/Actions/CreatePaymentIntentAction.php` | Creates Stripe PaymentIntent |
| Apply promotion | `app/Domain/Promotion/Actions/FindBestPromotionAction.php` | Find best discount |
| Request refund | `app/Domain/Refund/Actions/RequestRefundAction.php` | Creates refund request |

### Data Structures

| What | Where | Fields |
|------|-------|--------|
| Order model | `app/Domain/Order/Models/Order.php` | status, total_cents, items |
| Product model | `app/Domain/Product/Models/Product.php` | name, price_cents, sale_price |
| Cart model | `app/Domain/Cart/Models/Cart.php` | user_id, items |
| Stock model | `app/Domain/Inventory/Models/Stock.php` | quantity_available, quantity_reserved |

### Validation Rules

| What | Where |
|------|-------|
| Checkout validation | `app/Http/Requests/Api/V1/CheckoutRequest.php` |
| Cart item validation | `app/Http/Requests/Api/V1/AddToCartRequest.php` |
| Refund validation | `app/Http/Requests/Api/V1/RequestRefundRequest.php` |

### API Endpoints

| Endpoint | Controller | Purpose |
|----------|------------|---------|
| POST /api/v1/checkout | `CheckoutController@checkout` | Create order + payment |
| POST /api/v1/cart/items | `CartController@store` | Add item to cart |
| GET /api/v1/orders | `OrderController@index` | List user's orders |
| POST /api/v1/refunds | `RefundController@store` | Request refund |
| POST /api/v1/cart/apply-promotion | `PromotionController@apply` | Apply promo code |

### Frontend Pages

| Page | File | Props |
|------|------|-------|
| Product listing | `resources/js/Pages/Products/Index.vue` | products, categories, filters |
| Product detail | `resources/js/Pages/Products/Show.vue` | product, relatedProducts |
| Shopping cart | `resources/js/Pages/Cart/Index.vue` | cart |
| Checkout | `resources/js/Pages/Checkout/Summary.vue` | cart, clientSecret |
| Order history | `resources/js/Pages/Orders/Index.vue` | orders |

---

## Common Patterns

### Creating an Action

```php
// app/Domain/{Domain}/Actions/DoSomethingAction.php
final readonly class DoSomethingAction
{
    public function __construct(
        private SomeDependency $dep,
    ) {}

    public function execute(DoSomethingData $data): Result
    {
        // Business logic here
    }
}
```

### Creating a Specification

```php
// app/Domain/{Domain}/Specifications/SomeRule.php
final class SomeRule extends Specification
{
    public function isSatisfiedBy(mixed $subject): bool
    {
        return $subject->someCondition === true;
    }

    public function getFailureReason(): string
    {
        return 'The condition was not met.';
    }
}

// Usage
$spec = new SomeRule();
$spec->assertSatisfiedBy($entity); // Throws if fails
```

### Creating a Domain Event

```php
// app/Domain/{Domain}/Events/SomethingHappened.php
final readonly class SomethingHappened extends DomainEvent
{
    public function __construct(
        public int $entityId,
        public string $relevantData,
    ) {}
}

// Dispatch
event(new SomethingHappened($id, $data));
```

### Creating a Guard

```php
// app/Domain/{Domain}/Guards/EntityGuards.php
final class EntityGuards
{
    public static function canDoAction(Entity $entity): void
    {
        if ($entity->status !== ExpectedStatus::Value) {
            throw new InvalidStateException("Cannot do action in {$entity->status->value} state");
        }
    }
}

// Usage
EntityGuards::canDoAction($entity);
```

---

## Database Queries

### Get Order with Items
```php
$order = Order::with(['items', 'paymentIntent', 'refunds'])->find($id);
```

### Get Products with Stock
```php
$products = Product::with(['category', 'stock'])
    ->where('is_active', true)
    ->get();
```

### Lock Stock for Update
```php
DB::transaction(function () {
    $stock = Stock::where('product_id', $productId)
        ->lockForUpdate()  // Critical for concurrency
        ->first();

    $stock->quantity_available -= $quantity;
    $stock->save();
});
```

### Get User's Orders
```php
$orders = Order::where('user_id', $userId)
    ->with('items')
    ->orderByDesc('created_at')
    ->paginate(10);
```

---

## Status Enums

### OrderStatus
```php
enum OrderStatus: string {
    case Pending = 'pending';     // Awaiting payment
    case Paid = 'paid';           // Payment received
    case Shipped = 'shipped';     // Sent to customer
    case Completed = 'completed'; // Delivered
    case Cancelled = 'cancelled'; // Cancelled
}
```

### PaymentStatus
```php
enum PaymentStatus: string {
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
```

### RefundStatus
```php
enum RefundStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processed = 'processed';
    case Failed = 'failed';
}
```

### StockMovementType
```php
enum StockMovementType: string {
    case In = 'in';             // Stock received
    case Out = 'out';           // Stock shipped
    case Reserve = 'reserve';   // Stock reserved for order
    case Release = 'release';   // Reserved stock released
    case Adjustment = 'adjustment';
}
```

---

## Testing

### Run All Tests
```bash
php artisan test --compact
```

### Run Specific Test File
```bash
php artisan test tests/Feature/Api/V1/CheckoutApiTest.php
```

### Run Tests Matching Name
```bash
php artisan test --filter="creates order"
```

### Run Domain Tests
```bash
php artisan test tests/Feature/Domains/
```

### Mock Payment Gateway
```php
$this->mock(PaymentGatewayContract::class)
    ->shouldReceive('createIntent')
    ->andReturn(new ProviderResponse('stripe', 'pi_test', 'secret_test'));
```

---

## Artisan Commands

### Database
```bash
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Reset + seed
php artisan db:seed                    # Run seeders
```

### Cache
```bash
php artisan cache:clear               # Clear cache
php artisan route:clear               # Clear route cache
php artisan config:clear              # Clear config cache
php artisan view:clear                # Clear view cache
```

### Custom Commands
```bash
php artisan projections:replay        # Rebuild projections
php artisan audit:projection-drift    # Check data consistency
php artisan alerts:evaluate           # Check health metrics
```

### Code Quality
```bash
vendor/bin/pint                       # Fix code style
vendor/bin/pint --dirty               # Fix only changed files
vendor/bin/rector                     # Run automated refactoring
```

---

## Frontend

### Build for Development
```bash
npm run dev                           # Watch mode
```

### Build for Production
```bash
npm run build
```

### Type Check
```bash
npm run type-check                    # TypeScript check
```

---

## API Response Format

### Success
```json
{
    "data": {
        "id": 1,
        "order_number": "ORD-001",
        "status": "pending",
        "total_cents": 2500
    }
}
```

### Error
```json
{
    "error": {
        "code": "INSUFFICIENT_STOCK",
        "message": "Not enough stock available",
        "retryable": false
    }
}
```

### Paginated
```json
{
    "data": [...],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 10,
        "total": 50
    }
}
```

---

## Money Conventions

- **All money stored in cents** (integer)
- `$25.99` = `2599` cents
- `10%` = `1000` basis points
- Always use `int` type, never `float`

### Converting for Display
```php
// PHP
number_format($cents / 100, 2);  // "25.99"

// JavaScript
(cents / 100).toFixed(2);        // "25.99"

// With Intl
new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
}).format(cents / 100);          // "$25.99"
```

---

## Important Invariants

1. **Stock can never go negative** - Reserve/release pattern enforces this
2. **Order totals are immutable after creation** - Prices snapshot at checkout
3. **One active cart per user** - Enforced in `getOrCreateCart`
4. **Idempotency keys prevent duplicate checkouts** - Required header
5. **Refunds cannot exceed order total** - Specification validates

---

## File Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Action | `{Verb}{Noun}Action.php` | `CreateOrderAction.php` |
| DTO | `{Noun}Data.php` | `OrderData.php` |
| Event | `{Noun}{PastVerb}.php` | `OrderCreated.php` |
| Specification | `{Rule}.php` | `OrderIsRefundable.php` |
| Guard | `{Noun}Guards.php` | `OrderGuards.php` |
| Controller | `{Noun}Controller.php` | `OrderController.php` |
| Request | `{Verb}{Noun}Request.php` | `CreateOrderRequest.php` |
| Resource | `{Noun}Resource.php` | `OrderResource.php` |
| Test | `{Subject}Test.php` | `CreateOrderTest.php` |
