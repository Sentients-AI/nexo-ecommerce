# Database Schema — E-Commerce System

## Design principles

* **Correctness over convenience**
* **Immutable history for financial data**
* **Explicit handling of concurrency**
* **Separation of operational data vs audit data**
* **Tenant isolation via shared database with tenant_id**

Key invariants *(invariant — a condition that must always remain true)* are documented per domain.

---

## 0️⃣ Multi-Tenancy

### tenants

```sql
id                  BIGINT PK
name                VARCHAR(255)
slug                VARCHAR(100) UNIQUE INDEX
email               VARCHAR(255)
is_active           BOOLEAN INDEX DEFAULT true
settings            JSON
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

* Slug is used for subdomain identification (e.g., `acme-store.yourdomain.com`)
* Settings store tenant-specific configuration (e.g., currency, timezone)
* Inactive tenants cannot access the platform

**Tenant-Scoped Tables**

The following tables include a nullable `tenant_id` foreign key for data isolation:
- users, products, categories, stocks, stock_movements
- orders, order_items, carts, cart_items
- payment_intents, refunds, refund_events
- promotions, promotion_usages
- feature_flags, system_configs, idempotency_keys
- price_histories, order_financial_projections, refund_projections

**Tables WITHOUT tenant_id**

* `tenants` — The tenant table itself
* `roles` — Shared across all tenants
* Pivot tables (`category_product`, `promotion_product`, `promotion_category`) — Isolation via parent relationships
* System tables (`jobs`, `failed_jobs`, `cache`, `sessions`, `migrations`)

---

## 1️⃣ Users & Authorization

### users

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
email               VARCHAR(255) UNIQUE INDEX
password            VARCHAR(255)
name                VARCHAR(255)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

* Email is globally unique
* Authentication mechanism is decoupled from roles
* `tenant_id = NULL` indicates a super admin (platform-level access)
* Regular users must belong to a tenant

---

### roles

```sql
id                  BIGINT PK
name                VARCHAR(50) UNIQUE
```

**Examples**

* admin
* customer
* staff

---

### role_user

```sql
user_id             BIGINT FK → users.id INDEX
role_id             BIGINT FK → roles.id INDEX
PRIMARY KEY (user_id, role_id)
```

**Why**

* Explicit RBAC
* Avoids hard-coded permission logic

---

## 2️⃣ Product Catalog

### products

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
sku                 VARCHAR(100) INDEX
name                VARCHAR(255)
description         TEXT
price_cents         BIGINT
currency            CHAR(3)
is_active           BOOLEAN INDEX
created_at          TIMESTAMP
updated_at          TIMESTAMP
UNIQUE (tenant_id, sku)
```

**Invariants**

* `price_cents >= 0`
* SKU must be unique within a tenant

---

### categories

```sql
id                  BIGINT PK
name                VARCHAR(255)
slug                VARCHAR(255) UNIQUE
```

---

### category_product

```sql
category_id         BIGINT FK → categories.id INDEX
product_id          BIGINT FK → products.id INDEX
PRIMARY KEY (category_id, product_id)
```

**Why**

* Many-to-many classification
* Supports flexible catalog structure

---

## 3️⃣ Inventory (Critical Domain)

### stocks

```sql
id                  BIGINT PK
product_id          BIGINT FK → products.id UNIQUE
quantity_available  INTEGER
quantity_reserved   INTEGER
updated_at          TIMESTAMP
```

**Invariants**

* `quantity_available >= 0`
* `quantity_reserved >= 0`
* Stock row is locked during reservation

**Concurrency**

* Accessed using `SELECT … FOR UPDATE`

---

### stock_movements

```sql
id                  BIGINT PK
product_id          BIGINT FK → products.id INDEX
type                ENUM('IN','OUT','RESERVE','RELEASE')
quantity            INTEGER
reference_type      VARCHAR(100)
reference_id        BIGINT
created_at          TIMESTAMP
```

**Why**

* Full audit trail
* Enables reconciliation and debugging

---

## 4️⃣ Cart (Ephemeral State)

### carts

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id INDEX
status              ENUM('active','converted')
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

* Only one active cart per user (enforced at application level)

---

### cart_items

```sql
id                      BIGINT PK
cart_id                 BIGINT FK → carts.id INDEX
product_id              BIGINT FK → products.id
price_cents_snapshot    BIGINT
tax_cents_snapshot      BIGINT
quantity                INTEGER
```

**Invariant**

* Prices never change once added to cart

---

## 5️⃣ Orders (Immutable History)

### orders

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id INDEX
status              ENUM('pending','paid','cancelled','shipped','completed')
subtotal_cents      BIGINT
tax_cents           BIGINT
total_cents         BIGINT
currency            CHAR(3)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Invariants**

* Totals are immutable after `paid`
* Currency cannot change

---

### order_items

```sql
id                      BIGINT PK
order_id               BIGINT FK → orders.id INDEX
product_id             BIGINT FK → products.id
price_cents_snapshot   BIGINT
tax_cents_snapshot     BIGINT
quantity               INTEGER
```

**Why**

* Preserves historical pricing
* Orders survive product changes or deletion

---

## 6️⃣ Payments

### payments

```sql
id                      BIGINT PK
order_id               BIGINT FK → orders.id UNIQUE
provider                VARCHAR(50)
provider_reference      VARCHAR(255) INDEX
status                  ENUM('pending','succeeded','failed')
amount_cents            BIGINT
currency                CHAR(3)
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Notes**

* One payment per order (simplifies recovery logic)
* Provider reference indexed for webhook lookups

---

## 7️⃣ Idempotency (Advanced, High-Signal)

### idempotency_keys

```sql
id                  BIGINT PK
key                 VARCHAR(255) UNIQUE
user_id             BIGINT FK → users.id
response_hash       VARCHAR(255)
created_at          TIMESTAMP
```

**Why**

* Prevents duplicate orders
* Required for safe retries in distributed systems

---

## 8️⃣ Indexing Strategy (Explicit)

Recommended composite indexes:

```sql
orders (user_id, created_at)
stock_movements (product_id, created_at)
payments (provider_reference)
```

**Reason**

* Optimised for common read paths
* Improves operational diagnostics

---

## 9️⃣ Things intentionally NOT in the database

* Calculated totals (derived at order creation)
* Cached catalog responses
* Session state

These belong to **cache or application layers**, not persistence.

