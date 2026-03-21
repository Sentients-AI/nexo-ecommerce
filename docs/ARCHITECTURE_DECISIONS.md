# Architecture Decisions

This document explains the key architectural decisions made in this modular e-commerce system and the reasoning behind each choice.

> For the chronological decision log (what changed and when), see [DECISIONS.md](DECISIONS.md).

---

## Why This Architecture?

This system follows a **modular monolith** architecture with clear domain boundaries, positioned strategically between a traditional Laravel MVC application and a full microservices deployment.

### The Problem with Traditional MVC

Traditional Laravel applications often evolve into "big ball of mud" systems where:
- Controllers contain business logic mixed with HTTP concerns
- Models become "god objects" with 50+ methods
- Business rules scatter across controllers, models, and random service classes
- Testing requires spinning up the entire application
- Changes in one area cascade unpredictably to others

### The Problem with Premature Microservices

Jumping straight to microservices introduces:
- Network latency between every service call
- Distributed transaction complexity (saga patterns, eventual consistency)
- Operational overhead (deployment pipelines, service discovery, monitoring)
- Team coordination overhead before you have the team size to justify it

### The Modular Monolith Sweet Spot

This architecture provides:

```
┌─────────────────────────────────────────────────────────────┐
│                     Single Deployment                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐     │
│  │  Order   │  │ Payment  │  │Inventory │  │ Promotion│     │
│  │  Domain  │  │  Domain  │  │  Domain  │  │  Domain  │     │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘     │
│       │             │             │             │           │
│       └─────────────┴─────────────┴─────────────┘           │
│                         │                                   │
│              Shared Infrastructure                          │
│         (Database, Queue, Cache, Events)                    │
└─────────────────────────────────────────────────────────────┘
```

**Benefits realized:**
- **In-process communication**: Domain actions call each other directly—no network hops
- **Single database transaction**: Checkout creates order, reserves stock, and records promotion usage atomically
- **Clear boundaries**: Each domain owns its models, actions, and business rules
- **Extraction-ready**: Domains can become microservices when scale demands it
- **Simple operations**: One deployment, one database, one log stream

---

## Why DDD (Domain-Driven Design)?

DDD isn't about the tactical patterns (entities, value objects, repositories). It's about **aligning code structure with business reality**.

### Ubiquitous Language

The code speaks the same language as the business:

```php
// Not this (technical/CRUD thinking):
$orderService->processTransaction($userId, $cartItems, $paymentMethod);

// But this (domain thinking):
$order = $this->createOrderFromCart->execute($orderData);
$this->reserveStock->execute($reserveStockData);
$this->recordPromotionUsage->execute($promotion, $user, $order, $discountCents);
```

When a product manager says "the promotion usage should be recorded after the order is created," developers know exactly where that code lives.

### Bounded Contexts

Each domain has clear ownership:

```
app/Domain/
├── Cart/           # Shopping cart lifecycle
├── Order/          # Order creation, status transitions, fulfillment
├── Payment/        # Payment intents, provider integration, webhooks
├── Inventory/      # Stock levels, reservations, movements
├── Promotion/      # Discounts, codes, usage tracking
├── Refund/         # Refund requests, approval workflow
└── User/           # Authentication, profile, roles
```

**Why this matters:**
- New developers find code predictably
- Changes to promotions don't accidentally break inventory
- Each domain can evolve its internal structure independently

### Specifications Pattern

Business rules become composable, testable objects:

```php
// Complex business rule as a specification
$canCheckout = (new CartIsNotCompleted())
    ->and(new CartHasItems());

$canCheckout->assertSatisfiedBy($cart);  // Throws with clear message if invalid

// Promotion validation composes multiple rules
$validSpec = new PromotionIsValid();           // Active + date range + usage limit
$userSpec = new UserCanUsePromotion($promo);   // Per-user limit check
$cartSpec = new PromotionAppliesToCart($promo); // Scope + minimum order
```

**Benefits:**
- Rules are named (self-documenting)
- Rules are unit-testable in isolation
- Rules compose with `and()`, `or()`, `not()`
- Failure reasons are specific: "User has already used this promotion 3 time(s), limit is 3"

### Actions Over Services

Single-responsibility action classes instead of bloated service classes:

```php
// Not this (service class anti-pattern):
class OrderService {
    public function create() { }
    public function cancel() { }
    public function ship() { }
    public function refund() { }
    public function calculateTax() { }
    public function sendNotification() { }
    // ... 30 more methods
}

// But this (focused actions):
final readonly class CreateOrderFromCart { public function execute(CreateOrderData $data): Order }
final readonly class CalculateDiscountAction { public function execute(Cart $cart, Promotion $promo): DiscountCalculationResult }
final readonly class RecordPromotionUsageAction { public function execute(Promotion $promo, User $user, Order $order, int $discountCents): PromotionUsage }
```

**Benefits:**
- Each action has one reason to change
- Dependencies are explicit in the constructor
- Easy to test—mock only what that action needs
- Easy to find—action names match business operations

---

## Why Projections?

Projections solve the **query complexity problem** in event-driven systems.

### The Problem

Consider answering: "What's the financial status of order #12345?"

Without projections, you must:
1. Load the order
2. Load all payment intents
3. Load all refunds
4. Load all refund events
5. Calculate: paid - refunded = net

This is slow, complex, and the calculation logic duplicates across the codebase.

### The Solution: Read-Optimized Projections

```php
// OrderFinancialProjection - pre-calculated, always current
Schema::create('order_financial_projections', function (Blueprint $table) {
    $table->foreignId('order_id')->primary();
    $table->bigInteger('total_amount');
    $table->bigInteger('paid_amount');
    $table->bigInteger('refunded_amount');
    $table->string('refund_status');  // none, partial, full
    $table->timestamps();
});
```

Now the query is trivial:
```php
$financials = OrderFinancialProjection::find($orderId);
// Instant access to pre-calculated state
```

### Event-Driven Updates

Projections update via domain events:

```php
// When a refund succeeds, update the projection
class UpdateOrderFinancialsOnRefund
{
    public function handle(RefundSucceeded $event): void
    {
        $projection = OrderFinancialProjection::findOrFail($event->orderId);
        $projection->refunded_amount += $event->amountCents;
        $projection->refund_status = $this->calculateStatus($projection);
        $projection->save();
    }
}
```

### Rebuild Capability

Projections can be rebuilt from events if they drift or schema changes:

```php
// Replay all domain events to rebuild projections
php artisan projections:replay --projection=OrderFinancialProjection
```

### When to Use Projections

| Use Case | Solution |
|----------|----------|
| Simple CRUD reads | Query the model directly |
| Complex aggregations | Projection |
| Cross-domain queries | Projection |
| Audit/compliance reporting | Projection from events |
| Real-time dashboards | Projection with event-driven updates |

---

## Why Filament as Control Plane?

The **control plane** is the internal admin interface for operations, support, and finance teams—distinct from the customer-facing storefront.

### Filament's Strengths

**1. Rapid Development**
```php
// Complete CRUD in ~50 lines
final class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure($table);
    }
}
```

**2. Built-in Features**
- Authentication and authorization (integrates with policies)
- Form validation with Laravel rules
- Table filtering, sorting, searching
- Bulk actions
- Relationship management
- Activity logging hooks

**3. Customization Without Ejecting**
Unlike some admin panels that require "ejecting" to customize, Filament allows:
- Custom form components
- Custom table columns
- Custom pages
- Custom widgets
- Full Livewire/Alpine.js integration

**4. Separation of Concerns**

```
┌─────────────────────────────────────────────────────────┐
│                    Control Plane                         │
│                   (Filament Admin)                       │
│  • Manage products, inventory, orders                    │
│  • Process refunds                                       │
│  • Configure promotions                                  │
│  • View reports                                          │
└─────────────────────────────────────────────────────────┘
                          │
                    Domain Layer
                          │
┌─────────────────────────────────────────────────────────┐
│                    Customer Plane                        │
│                   (API / Frontend)                       │
│  • Browse products                                       │
│  • Shopping cart                                         │
│  • Checkout                                              │
│  • Order history                                         │
└─────────────────────────────────────────────────────────┘
```

Both planes use the **same domain layer**—Filament calls the same `CreatePromotionAction` that an API would. No business logic lives in the admin panel.

### Why Not Build Custom Admin?

Building admin interfaces is deceptively expensive:
- Table with sorting, filtering, pagination: 2-3 days
- Form with validation, relationships: 1-2 days
- Auth, roles, permissions: 1-2 days
- Per resource: multiply above

Filament provides this out of the box, letting developers focus on domain logic.

---

## Why Idempotency Everywhere?

Idempotency ensures that **repeating an operation produces the same result as executing it once**.

### The Reality of Distributed Systems

Networks fail. Users double-click. Queues retry. Webhooks duplicate.

```
Client                    Server
   │                         │
   ├──── POST /checkout ────►│
   │         ✕ timeout       │ (order created)
   │                         │
   ├──── POST /checkout ────►│ (retry)
   │                         │
   │◄─── 200 OK ─────────────┤
```

Without idempotency: two orders created, double charge.
With idempotency: second request returns cached response from first.

### Implementation Pattern

```php
// Controller level
public function checkout(CheckoutRequest $request): JsonResponse
{
    $idempotencyKey = $request->header('Idempotency-Key');

    // Check for cached response
    $cached = $this->ensureIdempotent->execute(
        $idempotencyKey,
        $user->id,
        'checkout',
        $request->validated()
    );

    if ($cached !== null) {
        return response()->json($cached);  // Return same response
    }

    // Process request...
    $response = $this->checkoutUseCase->execute($request);

    // Cache response for future duplicate requests
    $this->storeIdempotency->execute(
        $idempotencyKey,
        $user->id,
        'checkout',
        $request->validated(),
        200,
        $response
    );

    return response()->json($response);
}
```

### What Gets Idempotency Keys?

| Operation | Idempotent? | Why |
|-----------|-------------|-----|
| GET requests | Naturally | Read-only |
| Checkout | Yes, with key | Creates order + payment |
| Payment confirmation | Yes, with key | Charges card |
| Refund initiation | Yes, with key | Money movement |
| Add to cart | Yes, naturally | Upserts quantity |
| Update profile | Yes, naturally | Overwrites state |

### Request Fingerprinting

The idempotency system also validates that retry requests match the original:

```php
class RequestFingerprint
{
    public static function generate(string $operation, array $payload): string
    {
        return hash('sha256', $operation . json_encode($payload));
    }
}

// If someone sends same idempotency key with different payload:
// → Conflict error (409), not silent data corruption
```

### Database Design

```php
Schema::create('idempotency_keys', function (Blueprint $table) {
    $table->id();
    $table->string('key');
    $table->foreignId('user_id');
    $table->string('operation');
    $table->string('fingerprint');
    $table->integer('status_code');
    $table->json('response');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->unique(['key', 'user_id', 'operation']);
});
```

Keys expire after a reasonable window (e.g., 24 hours) to prevent unbounded storage growth.

---

## Why Shared Database Multi-Tenancy?

This system supports multiple tenants (independent stores) sharing a single database with row-level isolation.

### The Multi-Tenancy Spectrum

```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Single Tenant  │  │ Shared Database │  │ Database per    │
│  (Simple)       │  │ (This System)   │  │ Tenant          │
└─────────────────┘  └─────────────────┘  └─────────────────┘
     Simple              Balanced              Complex
     No isolation        Row isolation         Full isolation
     No overhead         Moderate overhead     High overhead
```

### Why Shared Database?

**Operational Simplicity**
- Single database to backup, migrate, and monitor
- No connection pooling per tenant
- Easy cross-tenant reporting for platform admins

**Implementation**
```php
// BelongsToTenant trait adds automatic scoping
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-set tenant_id on create
        static::creating(function (Model $model) {
            if ($model->tenant_id === null) {
                $model->tenant_id = Context::get('tenant_id');
            }
        });

        // Auto-filter queries by tenant
        static::addGlobalScope(new TenantScope());
    }
}

// TenantScope filters all queries
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = Context::get('tenant_id');
        if ($tenantId !== null) {
            $builder->where('tenant_id', $tenantId);
        }
    }
}
```

**Bypassing for Admin Operations**
```php
// Super admins need to see all tenants
$allOrders = Order::withoutTenancy()->get();

// Or query specific tenant
$tenantOrders = Order::withoutTenancy()
    ->where('tenant_id', $specificTenantId)
    ->get();
```

### Tenant Resolution

```
┌─────────────────────────────────────────────────────────────┐
│                     Request Arrives                          │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
     ┌─────────────────┐             ┌─────────────────┐
     │  Web Request    │             │  API Request    │
     │  (Subdomain)    │             │  (Auth Token)   │
     └────────┬────────┘             └────────┬────────┘
              │                               │
              ▼                               ▼
     ┌─────────────────┐             ┌─────────────────┐
     │ ResolveTenant   │             │ ResolveTenant   │
     │ FromSubdomain   │             │ FromUser        │
     └────────┬────────┘             └────────┬────────┘
              │                               │
              └───────────────┬───────────────┘
                              ▼
                    ┌─────────────────┐
                    │ Context::add(   │
                    │   'tenant_id',  │
                    │   $tenant->id   │
                    │ )               │
                    └─────────────────┘
```

### Queue Job Context Propagation

Tenant context automatically propagates to queued jobs:

```php
// In AppServiceProvider
Context::dehydrating(function (ContextDehydrating $event) {
    $event->setHidden('tenant_id', Context::get('tenant_id'));
});

Context::hydrated(function (ContextHydrated $event) {
    $tenantId = $event->getHidden('tenant_id');
    if ($tenantId) {
        Context::add('tenant_id', $tenantId);
    }
});
```

### Trade-offs Accepted

| Benefit | Trade-off |
|---------|-----------|
| Simple operations | Must remember to scope queries |
| Easy cross-tenant queries | Composite unique indexes needed |
| Single migration path | Cannot use database-level isolation |
| Shared connection pool | Noisy neighbor risk (mitigated by query limits) |

---

## Why an Append-Only Ledger for Loyalty Points?

Points are money-adjacent. The same principles that govern financial ledgers apply here.

### The Problem with Mutable Balances

A naive implementation mutates a single balance column:

```sql
UPDATE users SET points_balance = points_balance + 500 WHERE id = 42;
```

This is dangerous:
- **No audit trail** — impossible to answer "why does this user have 1250 points?"
- **Race conditions** — two concurrent awards can lose one update without `lockForUpdate`
- **No undo** — correcting a wrong award requires a manual DB patch

### The Ledger Pattern

Two tables work together:

```
loyalty_accounts                    loyalty_transactions
─────────────────────               ─────────────────────────────
id       user_id  balance           id  account_id  type    points  balance_after
──────── ──────── ────────          ── ──────────── ──────  ──────  ─────────────
1        42       1250              1   1           earned   500     500
                                    2   1           earned   750    1250
                                    3   1           redeemed -200   1050   ← future
```

`balance` is a **snapshot** — updated with every transaction to avoid recalculating from the full ledger on every request. The ledger is **append-only** — never updated or deleted.

### Concurrency Safety

`RedeemPointsAction` uses `lockForUpdate` before checking balance:

```php
DB::transaction(function () use ($data) {
    $account = LoyaltyAccount::where('user_id', $data->userId)
        ->lockForUpdate()  // Prevents concurrent over-redemption
        ->firstOrFail();

    if (! $account->canRedeem($data->points)) {
        throw new InsufficientPointsException($account->points_balance, $data->points);
    }

    $account->decrement('points_balance', $data->points);
    $account->increment('total_points_redeemed', $data->points);

    LoyaltyTransaction::create([
        'type'          => TransactionType::Redeemed,
        'points'        => -$data->points,
        'balance_after' => $account->points_balance,
    ]);
});
```

Without the lock, two simultaneous redemption requests can both read `balance = 200`, both pass the `canRedeem(200)` check, and together drain 400 points from a 200-point balance.

---

## Why Time-Limited, Usage-Capped Referral Codes?

### The Abuse Vector

An unlimited, perpetual referral code is an open invitation to abuse:

- Code gets posted on coupon sites → thousands of strangers use it → referrer earns tens of thousands of points
- Self-referral with a second account → free points

The system addresses both:

```
Code: ABCDEF123456
├── expires_at: 30 days from creation   ← time cap
├── max_uses: 10                        ← usage cap
└── SelfReferralException               ← same user_id check
```

### Atomicity of the Apply Operation

`ApplyReferralCodeAction` wraps five writes in a single transaction:

```
DB::transaction {
  1. Create ReferralUsage record
  2. Increment referral_codes.used_count
  3. AwardPointsAction → create LoyaltyTransaction (referrer)
  4. Generate coupon code string (referee)
  5. AuditLog::log('referral.code_applied')
}
```

If any step fails — say the loyalty account write fails — all five roll back. The referrer never receives points for a usage that wasn't recorded.

### Idempotency at the DB Level

The `referral_usages` table has a unique constraint:

```sql
UNIQUE (tenant_id, referral_code_id, referee_user_id)
```

Even if `ReferralAlreadyUsedException` is somehow bypassed at the application layer, the database enforces that one referee can never use the same code twice.

### Future Work: Wiring Coupons to Promotions

Currently `referee_coupon_code` is a plain string (e.g., `REF-XXXXXXXX`) stored on the usage record. The next step is to create a real `Promotion` record in the `promotions` table when the code is applied, so the discount is enforced at checkout rather than just displayed as a string. See [GitHub issue tracker] for this enhancement.

---

## Why Typesense Instead of a LIKE Query?

### The Limits of `LIKE '%query%'`

```sql
SELECT * FROM products WHERE name LIKE '%orgnic coffe%';
-- Returns 0 rows — typo kills the search
```

`LIKE` queries have three hard limits:

| Limit | Impact |
|---|---|
| No typo tolerance | "orgnic" finds nothing |
| Full-table scan | Slows as catalogue grows |
| No relevance ranking | "coffee beans" and "decaf coffee" equally match "coffee" |

### Why Typesense over Meilisearch or Elasticsearch?

| | Typesense | Meilisearch | Elasticsearch |
|---|---|---|---|
| Setup complexity | Low | Low | High |
| Laravel Scout driver | First-party | First-party | Third-party |
| Typo tolerance | Yes | Yes | Yes |
| Multi-tenant filter | Per-query `filter_by` | Per-query `filter` | Per-query |
| Self-host resource use | ~50MB RAM | ~100MB RAM | ~500MB RAM |
| Cloud offering | Yes | Yes | Yes |

Typesense was chosen for its low resource footprint and the first-party Scout driver.

### Tenant Isolation — the Critical Gotcha

Eloquent's `TenantScope` global scope **does not apply** to Typesense queries. Typesense receives its query directly — there is no Eloquent builder involved when fetching IDs from the search engine.

The fix is always explicit:

```php
// Wrong — returns results from ALL tenants
Product::search($query)->get();

// Correct — scoped to current tenant
Product::search($query)
    ->where('tenant_id', Context::get('tenant_id'))
    ->where('is_active', true)
    ->get();
```

`tenant_id` must be present in `toSearchableArray()` for this filter to work:

```php
public function toSearchableArray(): array
{
    return [
        'id'        => (string) $this->id,   // Typesense requires string IDs
        'tenant_id' => (int) $this->tenant_id,
        'name'      => $this->name,
        // ...
        'created_at' => $this->created_at->timestamp, // Must be UNIX int
    ];
}
```

### Search Index Lifecycle

```
Database record saved
        │
        ▼ (via Scout observer, queued)
Typesense index updated
        │
        ▼ (sub-50ms)
User search returns result
```

The queue (`queue = true` in `config/scout.php`) decouples the index write from the HTTP response. A product update is visible in search results within seconds of the queue worker processing the job, not instantly — which is acceptable for a product catalogue.

---

## Summary

| Decision | Problem Solved | Trade-off Accepted |
|----------|----------------|-------------------|
| Modular Monolith | Complexity of microservices, chaos of traditional MVC | Must maintain discipline on boundaries |
| DDD | Business logic scattered, hard to find code | More files, steeper learning curve |
| Projections | Slow complex queries, calculation duplication | Eventual consistency, rebuild complexity |
| Filament | Admin panel development time | Tied to Filament's upgrade cycle |
| Idempotency | Duplicate requests, network failures | Storage overhead, implementation complexity |
| Shared DB Multi-tenancy | Operational complexity of DB-per-tenant | Must enforce row-level isolation |
| Loyalty Ledger | Mutable balance race conditions and no audit trail | Two writes per event, snapshot drift risk |
| Referral Caps | Abuse via public code sharing | Codes expire, limiting long-term viral spread |
| Typesense Search | LIKE queries are slow and typo-intolerant | External service dependency, index sync lag |

These decisions optimize for:
1. **Developer productivity**: Find code fast, change code safely
2. **Business alignment**: Code matches how the business thinks
3. **Operational reliability**: Handle real-world failure modes
4. **Future flexibility**: Extract to microservices when needed, not before

The architecture isn't about following patterns for their own sake—it's about building a system that remains maintainable as the business grows and requirements evolve.
