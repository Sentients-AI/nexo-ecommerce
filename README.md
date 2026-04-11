# Nexo — Modular E-Commerce System

**Live:** https://store.aljebal-albeedos.com

A Domain-Driven Design (DDD) **multi-tenant** e-commerce platform built with Laravel 12, featuring strict invariant enforcement, event-driven architecture, real-time chat, product reviews, internationalization, and comprehensive business rule validation.

## Features

- **Multi-tenancy** — Shared database with subdomain isolation (`store.yourdomain.com`)
- **Product Catalog** — Products, categories, price history, sale pricing
- **Product Variants** — Size/colour/material options per product; variant-level SKU, price, and stock
- **Shopping Cart** — Anonymous + authenticated carts with session merging
- **Abandoned Cart Recovery** — Detect carts idle 24 h+ and send automated recovery email sequences
- **Checkout** — Idempotent checkout with Stripe PaymentIntent flow
- **Multi-Currency** — Per-tenant base currency with live exchange rates (Frankfurter/ECB); all prices converted at checkout; full audit trail (`base_total_cents`, `exchange_rate`, `base_currency`) on every order
- **Inventory Management** — Pessimistic locking, stock movements audit trail
- **Order Processing** — Full lifecycle (pending → paid → shipped → fulfilled)
- **Shipment Tracking** — Tracking numbers, carrier links, and customer-facing tracking page
- **Refund Workflow** — Approval-gated refund processing with compensation actions
- **Promotions & Discounts** — BOGO, tiered, bundle, flash-sale, and code-based promotions
- **Loyalty Points** — Earn points on orders, redeem for discounts; full transaction ledger with expiry
- **Referral Links** — Time-limited, usage-capped shareable codes; both sides rewarded
- **Product Reviews** — Customer ratings, photos, replies, and helpful-vote system
- **Digital Products / Downloads** — Downloadable files (e-books, software licences) with secure token-gated download links post-payment
- **AI Product Recommendations** — "Customers also bought" using purchase, browse, and wishlist history
- **Back-in-Stock Waitlist** — Email subscription per product; `BackInStockNotification` fires when stock is replenished via `StockReplenished` event
- **Stock Availability Badge** — "Only N left!" urgency indicator on product cards and detail pages
- **Tax Zones** — Configurable tax zones and rates per tenant; applied automatically at checkout via `CalculateTax` action
- **Transactional Emails** — Order confirmation, shipped, refund approved, and welcome emails via Laravel Notifications
- **Shipping Methods & Rates** — Flat-rate and free-shipping rules per tenant; zone/method selection at checkout; cost stored on orders
- **Guest Checkout** — Unauthenticated checkout; optional account creation post-purchase
- **Vendor Self-Service Onboarding** — Public `/start` wizard: subdomain picker, store setup, automatic tenant + admin user creation, 14-day trial
- **Sitemap + SEO** — `/sitemap.xml` for products and categories; Open Graph, Twitter Card, JSON-LD Product structured data, and canonical URLs on product pages
- **Vendor Order Export** — CSV download of filtered orders from the vendor dashboard
- **Bulk Product Import** — Vendor/admin CSV upload for mass product creation
- **Wishlist** — Per-user product wishlists
- **Saved Addresses** — Multiple shipping addresses per user with default selection
- **Vendor Dashboard** — Full self-service: manage products, view orders, fulfilment, analytics, promotions, inventory, settings
- **Notification Center** — In-app notifications for order updates, refund approvals, loyalty milestones; real-time via Reverb
- **Store Browsing** — Public tenant store pages
- **Full-text Search** — Typesense-powered search via Laravel Scout (Products, Categories, Orders)
- **Real-time Chat** — WebSocket-powered customer-support conversations (Laravel Reverb)
- **Social Login** — Google OAuth via Laravel Socialite
- **Internationalization** — English, Arabic (RTL), and Malay locales
- **Product Bundles** — First-class bundle domain (Bundle + BundleItem); bundle price set at bundle level; add bundle as a single cart item; vendor bundle management
- **Subscription Products** — Recurring billing via Laravel Cashier + Stripe Billing; subscription plans, Stripe Checkout redirect, Stripe Billing Portal, trial support
- **Advanced Analytics Dashboard** — Filament admin page with stats widget, revenue trend chart, and top products widget
- **Fraud Detection Dashboard** — Filament admin page surfacing high-risk orders and anomaly signals
- **Admin Control Plane** — Filament 5 panel with tenant management, dashboards, and operations
- **Observability** — Metrics, alerting, audit logs, correlation IDs, performance budgets

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 / PHP 8.4 |
| Admin Panel | Filament 5 |
| Frontend | Vue 3 + Inertia.js v2 |
| Styling | Tailwind CSS v4 |
| Real-time | Laravel Reverb (WebSockets) |
| Payments | Stripe (via Laravel Cashier) |
| Auth | Laravel Sanctum + Google OAuth (Socialite) |
| Search | Typesense via Laravel Scout |
| Testing | Pest 4 |
| Queue | Laravel Queue |

## Architecture Overview

```
app/
├── Application/              # Use Cases & Application DTOs
│   ├── UseCases/            # Orchestration layer
│   │   ├── Order/           # CheckoutUseCase
│   │   ├── Payment/         # ProcessPaymentUseCase
│   │   └── Refund/          # RequestRefundUseCase
│   └── DTOs/                # Request/Response DTOs with Value Objects
│
├── Domain/                   # Core business logic
│   ├── Cart/                # Shopping cart bounded context
│   ├── Category/            # Product categorization
│   ├── Chat/                # Real-time customer conversations
│   ├── Config/              # System configuration management
│   ├── Currency/            # Exchange rate service (Frankfurter/ECB)
│   ├── FeatureFlag/         # Runtime feature toggles
│   ├── Idempotency/         # Duplicate request prevention
│   ├── Inventory/           # Stock management & movements
│   ├── Order/               # Order processing & state machine
│   ├── Payment/             # Payment handling & Stripe integration
│   ├── Product/             # Product catalog & variants
│   ├── Projections/         # Read-optimized event projections
│   ├── Loyalty/             # Loyalty points — earn, redeem, ledger
│   ├── Promotion/           # Discounts and promotional codes
│   ├── Referral/            # Referral links & reward distribution
│   ├── Refund/              # Refund management & approval workflow
│   ├── Review/              # Product reviews, photos, replies, votes
│   ├── Role/                # RBAC roles
│   ├── Tax/                 # Tax calculation
│   ├── Tenant/              # Multi-tenancy (tenant isolation)
│   └── User/                # User management & address book
│   └── {Domain}/
│       ├── Actions/         # Domain services/commands
│       ├── DTOs/            # Domain data transfer objects
│       ├── Enums/           # State machines
│       ├── Events/          # Domain events
│       ├── Models/          # Eloquent aggregates
│       ├── Specifications/  # Business rule validators
│       └── ValueObjects/    # Identity & value types
│
├── Filament/                 # Admin control plane
│   ├── Pages/               # Dashboard, SystemHealth, AuditLog, etc.
│   ├── Resources/           # CRUD resources for all domains
│   └── Widgets/             # Revenue, orders, tenant stats widgets
│
├── Infrastructure/           # External services
│   └── Payment/Stripe/      # Payment gateway implementation
│
└── Shared/                   # Cross-cutting concerns
    ├── Specifications/      # Specification pattern base
    ├── ValueObjects/        # AbstractId base class
    └── Domain/              # Domain event infrastructure
```

## Multi-Tenancy

The system supports multi-tenancy with a shared database architecture:

- **Subdomain identification**: Each tenant has a unique subdomain (e.g., `acme-store.yourdomain.com`)
- **Automatic data isolation**: All tenant-scoped models use the `BelongsToTenant` trait
- **Super admin access**: Platform administrators can view and manage all tenants
- **Tenant switcher**: Super admins can impersonate tenant views in the control plane
- **Queue propagation**: Tenant context automatically propagates to queued jobs via Laravel Context

```php
// Automatic tenant scoping via BelongsToTenant trait
$products = Product::all(); // Only returns current tenant's products

// Bypass for admin operations
$allProducts = Product::withoutTenancy()->get();
```

## Key DDD Patterns

### Specification Pattern

Business rules are encapsulated in composable Specification classes:

```php
use App\Domain\Order\Specifications\OrderIsRefundable;
use App\Domain\Refund\Specifications\RefundAmountIsValid;

$spec = (new OrderIsRefundable())
    ->and(new RefundAmountIsValid($amountCents));

$spec->assertSatisfiedBy($order); // Throws DomainException if invalid
```

### Identity Value Objects

Type-safe IDs prevent accidental ID confusion:

```php
use App\Domain\Order\ValueObjects\OrderId;
use App\Domain\User\ValueObjects\UserId;

$orderId = OrderId::fromInt(123);
$userId  = UserId::fromInt(456);

// Type system prevents accidental swaps between ID types
```

### Use Cases (Application Layer)

Complex operations are orchestrated by Use Cases:

```php
use App\Application\UseCases\Order\CheckoutUseCase;
use App\Application\DTOs\Request\CheckoutRequest;

$response = $useCase->execute(new CheckoutRequest(
    userId: UserId::fromInt($userId),
    cartId: CartId::fromInt($cartId),
));
```

### Actions Over Services

Single-responsibility action classes instead of bloated service classes:

```php
final readonly class CreateOrderFromCart { public function execute(CreateOrderData $data): Order {} }
final readonly class ReserveStockAction  { public function execute(ReserveStockData $data): void {} }
final readonly class RecordPromotionUsageAction { public function execute(...): PromotionUsage {} }
```

## Getting Started

### Requirements

- PHP 8.4+
- Composer
- Node.js 20+
- MySQL 8+ / MariaDB 10.6+ / PostgreSQL 15+
- Redis (optional — defaults to database driver for queues and cache)

> **Shared hosting (MariaDB):** Set `DB_ENGINE=InnoDB` in your `.env` if the server defaults to MyISAM.

### Installation

```bash
# Clone and install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set up database
php artisan migrate --seed

# Build frontend assets
npm run build

# Start development server
composer run dev
```

### Post-Deploy: Search Index Population

After a fresh deploy or a Typesense schema change, populate the search index. Use the
`scout:import-all` command (introduced in this project) to flush and re-import all
searchable models in one step:

```bash
# Flush existing data and re-import all models (recommended on fresh deploys)
php artisan scout:import-all --fresh

# Import without flushing (safe for incremental syncs)
php artisan scout:import-all
```

If you need to target a single model:

```bash
php artisan scout:flush  "App\Domain\Product\Models\Product"
php artisan scout:import "App\Domain\Product\Models\Product"
php artisan scout:flush  "App\Domain\Category\Models\Category"
php artisan scout:import "App\Domain\Category\Models\Category"
php artisan scout:flush  "App\Domain\Order\Models\Order"
php artisan scout:import "App\Domain\Order\Models\Order"
```

> **Important:** If `scout:import` is skipped after a deploy, searches return empty results
> silently — no error, just zero products found. The Typesense health widget in the admin
> Control Plane (System dashboard) shows the server status and document count per model so
> you can verify the index is populated.

### Environment Variables

```env
APP_URL=http://localhost
DB_CONNECTION=mysql

# Stripe
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Multi-tenancy
APP_BASE_DOMAIN=localhost

# WebSockets (Reverb)
REVERB_APP_ID=xxx
REVERB_APP_KEY=xxx
REVERB_APP_SECRET=xxx

# Search (Typesense — set to "database" to disable Typesense and use DB full-text search)
SCOUT_DRIVER=typesense
TYPESENSE_API_KEY=masterKey
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108

# Google OAuth
GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback

# Multi-currency (optional — defaults to https://api.frankfurter.app, no key required)
CURRENCY_API_URL=https://api.frankfurter.app
CURRENCY_CACHE_TTL=3600
```

### Demo Tenants (after seeding)

| Subdomain | Status |
|-----------|--------|
| `acme-store` | Active |
| `gadget-world` | Active |
| `fashion-hub` | Active |
| `old-shop` | Inactive |

## Internationalization

The frontend supports three locales accessed via URL prefix:

- `/en/...` — English (default)
- `/ar/...` — Arabic (RTL)
- `/ms/...` — Malay

## Testing

```bash
# Run all tests
php artisan test --compact

# Run specification tests
php artisan test --filter=Specification

# Run a specific test file
php artisan test tests/Feature/Api/V1/CheckoutApiTest.php

# Run with coverage
php artisan test --coverage
```

## Code Quality

```bash
# Fix code style
vendor/bin/pint --dirty

# Run automated refactoring
vendor/bin/rector
```

## Documentation

| Document | Purpose |
|----------|---------|
| [API.md](API.md) | REST API reference for all endpoints |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Database schema with design rationale |
| [INVARIANTS.md](INVARIANTS.md) | System invariants, guards, and state machines |
| [PRODUCTION_READINESS_REVIEW.md](PRODUCTION_READINESS_REVIEW.md) | Production checklist and failure modes |
| [Check.md](Check.md) | Frontend UX/architecture specification |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Chronological architectural decision log |
| [docs/ARCHITECTURE_DECISIONS.md](docs/ARCHITECTURE_DECISIONS.md) | Deep-dive architecture decisions with rationale |
| [docs/COMPREHENSIVE_CODEBASE_GUIDE.md](docs/COMPREHENSIVE_CODEBASE_GUIDE.md) | Full codebase walkthrough |
| [docs/QUICK_REFERENCE.md](docs/QUICK_REFERENCE.md) | Fast lookup for common patterns and file locations |
| [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) | Common issues and resolution steps |
