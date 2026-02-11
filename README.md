# Modular E-Commerce System

A Domain-Driven Design (DDD) **multi-tenant** e-commerce platform built with Laravel, featuring strict invariant enforcement, event-driven architecture, and comprehensive business rule validation.

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
│   ├── Inventory/           # Stock management
│   ├── Order/               # Order processing
│   ├── Payment/             # Payment handling
│   ├── Product/             # Product catalog
│   ├── Promotion/           # Discounts and promotions
│   ├── Refund/              # Refund management
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
$userId = UserId::fromInt(456);

// Type system prevents: $orderId->equals($userId) // Different types!
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

## Documentation

- [DECISIONS.md](DECISIONS.md) - Architectural decisions and rationale
- [INVARIANTS.md](INVARIANTS.md) - System invariants and guards
- [PRODUCTION_READINESS_REVIEW.md](PRODUCTION_READINESS_REVIEW.md) - Production checklist

## Testing

```bash
# Run all tests
php artisan test

# Run specification tests
php artisan test --filter=Specification

# Run with coverage
php artisan test --coverage
```
