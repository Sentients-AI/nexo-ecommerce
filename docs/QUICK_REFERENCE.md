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
| Award loyalty points | `app/Domain/Loyalty/Actions/AwardPointsAction.php` | `$action->execute($data)` |
| Redeem loyalty points | `app/Domain/Loyalty/Actions/RedeemPointsAction.php` | Throws `InsufficientPointsException` |
| Generate referral code | `app/Domain/Referral/Actions/GenerateReferralCodeAction.php` | Idempotent — returns existing if active |
| Apply referral code | `app/Domain/Referral/Actions/ApplyReferralCodeAction.php` | Awards points + coupon in one transaction |
| Start conversation | `app/Domain/Chat/Actions/` | Real-time chat actions |
| Submit review | `app/Domain/Review/Models/Review.php` | Product rating/body |
| Calculate tax | `app/Domain/Tax/Actions/CalculateTax.php` | `$action->execute($data)` |

### Multi-Tenancy

| What | Where |
|------|-------|
| Tenant model | `app/Domain/Tenant/Models/Tenant.php` |
| Tenant scope trait | `app/Domain/Tenant/Traits/BelongsToTenant.php` |
| Tenant global scope | `app/Domain/Tenant/Scopes/TenantScope.php` |
| Subdomain resolver | `app/Http/Middleware/ResolveTenantFromSubdomain.php` |
| API tenant resolver | `app/Http/Middleware/ResolveTenantFromUser.php` |
| Filament tenant filter | `app/Http/Middleware/FilamentTenantMiddleware.php` |
| Tenant switcher (Livewire) | `app/Livewire/TenantSwitcher.php` |
| Tenant config | `config/tenancy.php` |

### Data Structures

| What | Where | Key Fields |
|------|-------|-----------|
| Tenant model | `app/Domain/Tenant/Models/Tenant.php` | name, slug, email, is_active, settings |
| Order model | `app/Domain/Order/Models/Order.php` | tenant_id, status, total_cents, discount_cents, items |
| Product model | `app/Domain/Product/Models/Product.php` | tenant_id, name, slug, price_cents, sale_price_cents |
| Cart model | `app/Domain/Cart/Models/Cart.php` | tenant_id, user_id, status, completed_at |
| Stock model | `app/Domain/Inventory/Models/Stock.php` | tenant_id, quantity_available, quantity_reserved |
| User model | `app/Domain/User/Models/User.php` | tenant_id, name, email, roles |
| Review model | `app/Domain/Review/Models/Review.php` | product_id, user_id, rating, body |
| Conversation | `app/Domain/Chat/Models/Conversation.php` | user_id, subject, status |
| ChatMessage | `app/Domain/Chat/Models/ChatMessage.php` | conversation_id, sender_id, body, read_at |
| LoyaltyAccount | `app/Domain/Loyalty/Models/LoyaltyAccount.php` | tenant_id, user_id, points_balance, total_points_earned |
| LoyaltyTransaction | `app/Domain/Loyalty/Models/LoyaltyTransaction.php` | type, points, balance_after, reference_type/id |
| ReferralCode | `app/Domain/Referral/Models/ReferralCode.php` | code, max_uses, used_count, expires_at, is_active |
| ReferralUsage | `app/Domain/Referral/Models/ReferralUsage.php` | referrer_user_id, referee_user_id, coupon_code |

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
| POST /api/v1/cart/items | `CartController@addItem` | Add item to cart |
| GET /api/v1/orders | `OrderController@index` | List user's orders |
| POST /api/v1/orders/{order}/refunds | `RefundController@store` | Request refund |
| POST /api/v1/cart/apply-promotion | `PromotionController@apply` | Apply promo code |
| GET /api/v1/products/{slug}/reviews | `ReviewController@index` | List product reviews |
| POST /api/v1/products/{slug}/reviews | `ReviewController@store` | Submit a review |
| GET /api/v1/conversations | `ConversationController@index` | List conversations |
| POST /api/v1/conversations | `ConversationController@store` | Start conversation |
| POST /api/v1/conversations/{id}/messages | `MessageController@store` | Send message |
| GET /api/v1/loyalty | `LoyaltyController@index` | Get points balance |
| GET /api/v1/loyalty/transactions | `LoyaltyController@transactions` | Transaction history |
| POST /api/v1/loyalty/redeem | `LoyaltyController@redeem` | Redeem points |
| GET /api/v1/referral | `ReferralController@show` | Get / auto-create referral code |
| GET /api/v1/referral/stats | `ReferralController@stats` | Referral usage stats |
| POST /api/v1/referral/apply | `ReferralController@apply` | Apply a referral code |
| POST /api/v1/referral/regenerate | `ReferralController@regenerate` | Fresh code, same settings |
| GET /api/v1/search/products | `SearchController@products` | Typesense product search (public) |
| GET /api/v1/search/categories | `SearchController@categories` | Typesense category search (public) |
| GET /api/v1/search/orders | `SearchController@orders` | Typesense order search (auth) |

### Web Routes (Inertia)

| URL Pattern | Controller | Page |
|-------------|------------|------|
| `/{locale}/products` | `ProductController@index` | `Products/Index.vue` |
| `/{locale}/products/{slug}` | `ProductController@show` | `Products/Show.vue` |
| `/{locale}/stores/{slug}` | `StoreController@show` | `Stores/Show.vue` |
| `/{locale}/cart` | `CartController@index` | `Cart/Index.vue` |
| `/{locale}/wishlist` | `ProductController@wishlist` | `Wishlist/Index.vue` |
| `/{locale}/checkout` | `CheckoutController@summary` | `Checkout/Summary.vue` |
| `/{locale}/orders` | `OrderController@index` | `Orders/Index.vue` |
| `/{locale}/orders/{order}` | `OrderController@show` | `Orders/Show.vue` |

### Frontend Pages

| Page | File | Props |
|------|------|-------|
| Home | `resources/js/Pages/Home.vue` | — |
| Product listing | `resources/js/Pages/Products/Index.vue` | products, categories, filters |
| Product detail | `resources/js/Pages/Products/Show.vue` | product, reviews |
| Store page | `resources/js/Pages/Stores/Show.vue` | tenant, products |
| Shopping cart | `resources/js/Pages/Cart/Index.vue` | cart |
| Wishlist | `resources/js/Pages/Wishlist/Index.vue` | wishlisted products |
| Checkout | `resources/js/Pages/Checkout/Summary.vue` | cart, clientSecret |
| Order history | `resources/js/Pages/Orders/Index.vue` | orders |
| Order detail | `resources/js/Pages/Orders/Show.vue` | order |

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
$spec->assertSatisfiedBy($entity); // Throws DomainException if fails

// Composition
$combined = (new RuleA())->and(new RuleB());
$combined->assertSatisfiedBy($entity);
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

### Get Products with Stock and Reviews

```php
$products = Product::with(['categories', 'stock'])
    ->withAvg('reviews', 'rating')
    ->withCount('reviews')
    ->where('is_active', true)
    ->get();
```

### Lock Stock for Update

```php
DB::transaction(function () {
    $stock = Stock::where('product_id', $productId)
        ->lockForUpdate()  // Critical for concurrency
        ->firstOrFail();

    $stock->quantity_available -= $quantity;
    $stock->quantity_reserved  += $quantity;
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

### Multi-Tenancy Queries

```php
// Normal query (auto-filtered by tenant)
$products = Product::all();  // Only current tenant's products

// Bypass tenant scope (admin operations)
$allProducts = Product::withoutTenancy()->get();

// Query specific tenant
$tenantProducts = Product::withoutTenancy()
    ->where('tenant_id', $tenantId)
    ->get();

// Get current tenant ID
$tenantId = Context::get('tenant_id');

// Set tenant context manually (e.g., in tests or seeders)
Context::add('tenant_id', $tenant->id);
```

---

## Status Enums

### OrderStatus

```php
enum OrderStatus: string {
    case Pending   = 'pending';    // Awaiting payment
    case Paid      = 'paid';       // Payment received
    case Shipped   = 'shipped';    // Sent to customer
    case Completed = 'completed';  // Delivered
    case Cancelled = 'cancelled';  // Cancelled
}
```

### PaymentStatus

```php
enum PaymentStatus: string {
    case Processing = 'processing';
    case Succeeded  = 'succeeded';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
}
```

### RefundStatus

```php
enum RefundStatus: string {
    case Requested  = 'requested';
    case Approved   = 'approved';
    case Rejected   = 'rejected';
    case Processing = 'processing';
    case Succeeded  = 'succeeded';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
}
```

### StockMovementType

```php
enum StockMovementType: string {
    case In         = 'IN';
    case Out        = 'OUT';
    case Reserve    = 'RESERVE';
    case Release    = 'RELEASE';
    case Adjustment = 'ADJUSTMENT';
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

### Tenant Context in Tests

```php
// Use the WithTenant trait
use Tests\Traits\WithTenant;

beforeEach(fn () => $this->setUpTenant());

// Act as a user in a specific tenant
$this->actingAsUserInTenant($user, $tenant);
```

---

## Artisan Commands

### Database

```bash
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Reset + seed demo data
php artisan db:seed                    # Run seeders only
```

### Cache

```bash
php artisan cache:clear               # Clear application cache
php artisan route:clear               # Clear route cache
php artisan config:clear              # Clear config cache
php artisan view:clear                # Clear compiled views
```

### Search (Typesense / Scout)

```bash
# Seed Typesense index from existing DB records (run once after deploy)
php artisan scout:import "App\Domain\Product\Models\Product"
php artisan scout:import "App\Domain\Category\Models\Category"
php artisan scout:import "App\Domain\Order\Models\Order"

# Flush and re-import (after schema change)
php artisan scout:flush "App\Domain\Product\Models\Product"
php artisan scout:import "App\Domain\Product\Models\Product"
```

**Important:** Every Scout search call must pass `.where('tenant_id', Context::get('tenant_id'))` because Typesense bypasses Eloquent global scopes.

```php
// Correct — tenant-scoped Typesense search
Product::search($query)
    ->where('tenant_id', Context::get('tenant_id'))
    ->where('is_active', true)
    ->paginate(15);
```

### Custom Commands

```bash
php artisan projections:replay        # Rebuild read projections from events
php artisan audit:projection-drift    # Detect data consistency issues
php artisan alerts:evaluate           # Evaluate alert definitions against metrics
```

### Code Quality

```bash
vendor/bin/pint --dirty               # Fix style on changed files only
vendor/bin/pint                       # Fix all files
vendor/bin/rector                     # Run automated refactoring
```

---

## Frontend

### Development

```bash
npm run dev                           # Watch mode (hot reload)
composer run dev                      # Start Laravel + Vite + Reverb together
```

### Production

```bash
npm run build                         # Build and hash assets
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
        "retryable": false,
        "correlation_id": "uuid"
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
- Always use `int` type, never `float`

### Converting for Display

```php
// PHP
number_format($cents / 100, 2);  // "25.99"
```

```js
// JavaScript
(cents / 100).toFixed(2);

// With Intl API
new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })
    .format(cents / 100);  // "$25.99"
```

---

## Important Invariants

1. **Stock can never go negative** — Reserve/release with pessimistic locks
2. **Order totals are immutable after creation** — Prices snapshot at checkout
3. **One active cart per user** — Enforced in `getOrCreateCart`
4. **Idempotency keys prevent duplicate checkouts** — Required `Idempotency-Key` header
5. **Refunds cannot exceed order total** — Specification validates remaining amount
6. **Tenant data isolation** — All queries automatically filtered by `tenant_id`
7. **Super admins have no tenant** — `tenant_id = NULL` for platform admins
8. **One review per user per product** — Unique constraint on (product_id, user_id)
9. **Payment state is terminal** — Succeeded/Failed/Cancelled cannot transition
10. **Refund requires approval** — Must be Approved before Processing
11. **Loyalty points never go below zero** — `RedeemPointsAction` uses `lockForUpdate` and throws on deficit
12. **One referral usage per user per code** — DB unique constraint + `ReferralAlreadyUsedException`
13. **Self-referral is blocked** — `SelfReferralException` enforced before DB write
14. **Scout searches must filter by tenant_id** — Typesense bypasses Eloquent global scopes

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
