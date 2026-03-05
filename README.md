# Modular E-Commerce System

A Domain-Driven Design (DDD) **multi-tenant** e-commerce platform built with Laravel 12, featuring strict invariant enforcement, event-driven architecture, real-time chat, product reviews, internationalization, and comprehensive business rule validation.

## Features

- **Multi-tenancy** — Shared database with subdomain isolation (`store.yourdomain.com`)
- **Product Catalog** — Products, categories, price history, sale pricing
- **Shopping Cart** — Anonymous + authenticated carts with session merging
- **Checkout** — Idempotent checkout with Stripe PaymentIntent flow
- **Inventory Management** — Pessimistic locking, stock movements audit trail
- **Order Processing** — Full lifecycle (pending → paid → shipped → fulfilled)
- **Refund Workflow** — Approval-gated refund processing with compensation actions
- **Promotions & Discounts** — Code-based and rule-based promotion system
- **Product Reviews** — Customer ratings and reviews per tenant
- **Wishlist** — Per-user product wishlists
- **Store Browsing** — Public tenant store pages
- **Real-time Chat** — WebSocket-powered customer-support conversations (Laravel Reverb)
- **Internationalization** — English, Arabic (RTL), and Malay locales
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
| Auth | Laravel Sanctum |
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
│   ├── FeatureFlag/         # Runtime feature toggles
│   ├── Idempotency/         # Duplicate request prevention
│   ├── Inventory/           # Stock management & movements
│   ├── Order/               # Order processing & state machine
│   ├── Payment/             # Payment handling & Stripe integration
│   ├── Product/             # Product catalog
│   ├── Projections/         # Read-optimized event projections
│   ├── Promotion/           # Discounts and promotional codes
│   ├── Refund/              # Refund management & approval workflow
│   ├── Review/              # Product reviews & ratings
│   ├── Role/                # RBAC roles
│   ├── Tax/                 # Tax calculation
│   ├── Tenant/              # Multi-tenancy (tenant isolation)
│   └── User/                # User management
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
- MySQL 8+ or PostgreSQL 15+
- Redis (for queues and cache)

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

### Environment Variables

```env
APP_URL=http://localhost
DB_CONNECTION=mysql

# Stripe
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Multi-tenancy
TENANCY_BASE_DOMAIN=localhost

# WebSockets (Reverb)
REVERB_APP_ID=xxx
REVERB_APP_KEY=xxx
REVERB_APP_SECRET=xxx
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
| [DECISIONS.md](DECISIONS.md) | Architectural decision log with rationale |
| [INVARIANTS.md](INVARIANTS.md) | System invariants, guards, and state machines |
| [PRODUCTION_READINESS_REVIEW.md](PRODUCTION_READINESS_REVIEW.md) | Production checklist and failure modes |
| [Check.md](Check.md) | Frontend UX/architecture specification |
| [docs/ARCHITECTURE_DECISIONS.md](docs/ARCHITECTURE_DECISIONS.md) | Deep-dive architecture decisions |
| [docs/COMPREHENSIVE_CODEBASE_GUIDE.md](docs/COMPREHENSIVE_CODEBASE_GUIDE.md) | Full codebase walkthrough |
| [docs/QUICK_REFERENCE.md](docs/QUICK_REFERENCE.md) | Fast lookup for common patterns and file locations |
