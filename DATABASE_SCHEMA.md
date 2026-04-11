# Database Schema — E-Commerce System

## Design Principles

- **Correctness over convenience**
- **Immutable history for financial data**
- **Explicit handling of concurrency**
- **Separation of operational data vs audit data**
- **Tenant isolation via shared database with tenant_id**
- **Monetary values stored as integers (cents)**

Key invariants *(a condition that must always remain true)* are documented per domain.

---

## 0. Multi-Tenancy

### tenants

```sql
id                  BIGINT PK
name                VARCHAR(255)
slug                VARCHAR(100) UNIQUE INDEX
email               VARCHAR(255)
description         TEXT NULLABLE
is_active           BOOLEAN INDEX DEFAULT true
settings            JSON
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

- Slug is used for subdomain identification (e.g., `acme-store.yourdomain.com`)
- Settings store tenant-specific configuration (e.g., currency, timezone)
- Inactive tenants cannot access the platform

**Tenant-Scoped Tables**

The following tables include a nullable `tenant_id` foreign key for data isolation:
- users, user_addresses, products, product_variants, categories, stocks, stock_movements
- orders, order_items, carts, cart_items
- payment_intents, refunds, refund_events
- promotions, promotion_usages
- reviews, review_photos, review_replies, review_votes, conversations, chat_messages
- feature_flags, system_configs, idempotency_keys
- price_histories, order_financial_projections, refund_projections
- loyalty_accounts, loyalty_transactions
- referral_codes, referral_usages
- variant_attribute_types, variant_attribute_values, product_variant_attribute_values

**Tables WITHOUT tenant_id**

- `tenants` — The tenant table itself
- `roles` — Shared across all tenants
- Pivot tables (`category_product`, `promotion_product`, `promotion_category`) — Isolation via parent relationships
- System tables (`jobs`, `failed_jobs`, `cache`, `sessions`, `migrations`)
- Observability tables (`metrics`, `alert_definitions`, `alert_triggers`, `audit_logs`, `domain_events`)

---

## 1. Users & Authorization

### users

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
email               VARCHAR(255) UNIQUE INDEX
password            VARCHAR(255)
name                VARCHAR(255)
google_id           VARCHAR(255) NULLABLE UNIQUE INDEX
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

- Email is globally unique
- `tenant_id = NULL` indicates a super admin (platform-level access)
- Regular users must belong to a tenant
- `google_id` is populated on first Google OAuth login; `password` may be null for OAuth-only accounts

---

### roles

```sql
id                  BIGINT PK
name                VARCHAR(50) UNIQUE
```

**Examples:** admin, customer, staff

---

### role_user

```sql
user_id             BIGINT FK → users.id INDEX
role_id             BIGINT FK → roles.id INDEX
PRIMARY KEY (user_id, role_id)
```

---

### personal_access_tokens

Standard Laravel Sanctum table for API token management.

---

## 2. Product Catalog

### products

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
sku                 VARCHAR(100) INDEX
name                VARCHAR(255)
slug                VARCHAR(255)
description         TEXT
price_cents         BIGINT
sale_price_cents    BIGINT NULLABLE
currency            CHAR(3)
is_active           BOOLEAN INDEX
view_count          INTEGER DEFAULT 0
created_at          TIMESTAMP
updated_at          TIMESTAMP
UNIQUE (tenant_id, sku)
```

**Invariants**

- `price_cents >= 0`
- `sale_price_cents >= 0` when set
- SKU must be unique within a tenant

---

### categories

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
name                VARCHAR(255)
slug                VARCHAR(255) UNIQUE
description         TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### category_product

```sql
category_id         BIGINT FK → categories.id INDEX
product_id          BIGINT FK → products.id INDEX
PRIMARY KEY (category_id, product_id)
```

---

### price_histories

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
product_id          BIGINT FK → products.id INDEX
price_cents         BIGINT
sale_price_cents    BIGINT NULLABLE
changed_by          BIGINT FK → users.id NULLABLE
created_at          TIMESTAMP
```

**Why**

- Immutable price change audit trail
- Supports repricing analytics and dispute resolution

---

### reviews

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
product_id          BIGINT FK → products.id INDEX
user_id             BIGINT FK → users.id INDEX
rating              TINYINT (1–5)
body                TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
UNIQUE (product_id, user_id)
```

**Invariants**

- One review per user per product
- Rating must be between 1 and 5

---

## 3. Inventory (Critical Domain)

### stocks

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
product_id          BIGINT FK → products.id UNIQUE
quantity_available  INTEGER
quantity_reserved   INTEGER
updated_at          TIMESTAMP
```

**Invariants**

- `quantity_available >= 0`
- `quantity_reserved >= 0`
- Stock row is locked during reservation via `SELECT ... FOR UPDATE`

---

### stock_movements

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
product_id          BIGINT FK → products.id INDEX
type                ENUM('IN','OUT','RESERVE','RELEASE','ADJUSTMENT')
quantity            INTEGER
reference_type      VARCHAR(100)
reference_id        BIGINT
user_id             BIGINT FK → users.id NULLABLE
created_at          TIMESTAMP
```

**Why**

- Full audit trail of all inventory changes
- Enables reconciliation and debugging
- `user_id` tracks who made manual adjustments

---

## 4. Cart (Ephemeral State)

### carts

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
user_id             BIGINT FK → users.id INDEX
status              ENUM('active','converted')
completed_at        TIMESTAMP NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

- Only one active cart per user (enforced at application level)
- `completed_at` is set when cart converts to an order

---

### cart_items

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
cart_id                 BIGINT FK → carts.id INDEX
product_id              BIGINT FK → products.id
price_cents_snapshot    BIGINT
tax_cents_snapshot      BIGINT
quantity                INTEGER
```

**Invariant**

- Prices are snapshotted at time of addition and never change

---

## 5. Orders (Immutable History)

### orders

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
user_id                 BIGINT FK → users.id INDEX
status                  ENUM('pending','paid','shipped','completed','cancelled')
subtotal_cents          BIGINT
tax_cents               BIGINT
shipping_cost_cents     BIGINT DEFAULT 0
discount_cents          BIGINT DEFAULT 0
loyalty_discount_cents  BIGINT DEFAULT 0
total_cents             BIGINT
currency                CHAR(3)           -- checkout currency (may differ from base)
base_currency           CHAR(3)           -- tenant's base currency at time of order
exchange_rate           DECIMAL(10,6)     -- rate used: base → currency
base_total_cents        BIGINT            -- total in base currency (for reporting)
refunded_amount_cents   BIGINT DEFAULT 0
promotion_id            BIGINT FK → promotions.id NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Invariants**

- Totals are immutable after `paid`
- `currency`, `base_currency`, and `exchange_rate` cannot change once set
- `discount_cents` reflects promotion applied at checkout
- `base_total_cents * exchange_rate ≈ total_cents` (rounded to integer)
- When `currency == base_currency`, `exchange_rate = 1.000000` exactly

---

### order_items

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
order_id                BIGINT FK → orders.id INDEX
product_id              BIGINT FK → products.id
price_cents_snapshot    BIGINT
tax_cents_snapshot      BIGINT
quantity                INTEGER
```

**Why**

- Preserves historical pricing
- Orders survive product changes or deletion

---

## 6. Payments

### payment_intents

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
order_id                BIGINT FK → orders.id UNIQUE
provider                VARCHAR(50)
provider_reference      VARCHAR(255) INDEX
client_secret           VARCHAR(255) NULLABLE
status                  ENUM('processing','succeeded','failed','cancelled')
amount_cents            BIGINT
currency                CHAR(3)
attempts                INTEGER DEFAULT 0
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Notes**

- One payment intent per order (simplifies recovery logic)
- Provider reference indexed for webhook lookups
- `attempts` tracks confirmation attempts for monitoring

---

## 7. Refunds

### refunds

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
order_id                BIGINT FK → orders.id INDEX
amount_cents            BIGINT
currency                CHAR(3)
reason                  TEXT NULLABLE
status                  ENUM('requested','approved','rejected','processing','succeeded','failed','cancelled')
provider_reference      VARCHAR(255) NULLABLE INDEX
approved_by             BIGINT FK → users.id NULLABLE
approved_at             TIMESTAMP NULLABLE
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Invariants**

- Total refunded amount across all refunds must not exceed `orders.total_cents`
- `provider_reference` required once status is `succeeded`

---

### refund_events

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
refund_id           BIGINT FK → refunds.id INDEX
event_type          VARCHAR(100)
payload             JSON
created_at          TIMESTAMP
```

**Why**

- Immutable event log for refund lifecycle
- Supports audit, debugging, and dispute resolution
- Retention: 2 years

---

## 8. Loyalty Points

### loyalty_accounts

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id INDEX
user_id                 BIGINT FK → users.id
points_balance          INT UNSIGNED DEFAULT 0
total_points_earned     INT UNSIGNED DEFAULT 0
total_points_redeemed   INT UNSIGNED DEFAULT 0
created_at              TIMESTAMP
updated_at              TIMESTAMP
UNIQUE (tenant_id, user_id)
```

**Notes**

- One account per user per tenant; auto-created on first point award
- `points_balance` is the spendable balance; never goes negative

---

### loyalty_transactions

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id INDEX
user_id             BIGINT FK → users.id
loyalty_account_id  BIGINT FK → loyalty_accounts.id
type                ENUM('earned','redeemed','expired','adjustment','refunded')
points              INT (positive = credit, negative = debit)
balance_after       INT UNSIGNED
description         VARCHAR(255) NULLABLE
reference_type      VARCHAR(255) NULLABLE
reference_id        BIGINT UNSIGNED NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
INDEX (reference_type, reference_id)
```

**Why**

- Immutable ledger — never update, only append
- `reference_type/id` polymorphic link to originating entity (order, referral_usage, etc.)

---

## 8a. Referral Links

### referral_codes

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id INDEX
user_id                 BIGINT FK → users.id
code                    VARCHAR(12)
referrer_reward_points  INT UNSIGNED DEFAULT 500
referee_discount_percent TINYINT UNSIGNED DEFAULT 10
max_uses                INT UNSIGNED NULLABLE
used_count              INT UNSIGNED DEFAULT 0
expires_at              TIMESTAMP NULLABLE
is_active               BOOLEAN DEFAULT true
created_at              TIMESTAMP
updated_at              TIMESTAMP
UNIQUE (tenant_id, code)
INDEX (tenant_id, user_id)
```

**Invariants**

- `used_count <= max_uses` (when max_uses is not null)
- `expires_at` null means the code never expires
- `max_uses` null means unlimited uses

---

### referral_usages

```sql
id                          BIGINT PK
tenant_id                   BIGINT FK → tenants.id
referral_code_id            BIGINT FK → referral_codes.id
referrer_user_id            BIGINT FK → users.id
referee_user_id             BIGINT FK → users.id
referrer_points_awarded     INT UNSIGNED
referee_discount_percent    TINYINT UNSIGNED
referee_coupon_code         VARCHAR(20) NULLABLE
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
UNIQUE referral_usages_unique (tenant_id, referral_code_id, referee_user_id)
```

**Invariants**

- One usage per `(referee_user_id, referral_code_id)` — prevents double-dipping
- `referrer_user_id != referee_user_id` — self-referral is blocked at application level

---

## 9. Promotions

### promotions

```sql
id                      BIGINT PK
tenant_id               BIGINT FK → tenants.id NULLABLE INDEX
code                    VARCHAR(100) INDEX
description             TEXT NULLABLE
discount_type           ENUM('fixed','percentage')
discount_value          INTEGER
minimum_order_cents     BIGINT DEFAULT 0
usage_limit             INTEGER NULLABLE
per_user_limit          INTEGER DEFAULT 1
starts_at               TIMESTAMP NULLABLE
expires_at              TIMESTAMP NULLABLE
is_active               BOOLEAN DEFAULT true
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

### promotion_product

```sql
promotion_id        BIGINT FK → promotions.id
product_id          BIGINT FK → products.id
PRIMARY KEY (promotion_id, product_id)
```

---

### promotion_category

```sql
promotion_id        BIGINT FK → promotions.id
category_id         BIGINT FK → categories.id
PRIMARY KEY (promotion_id, category_id)
```

---

### promotion_usages

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
promotion_id        BIGINT FK → promotions.id INDEX
user_id             BIGINT FK → users.id INDEX
order_id            BIGINT FK → orders.id INDEX
discount_cents      BIGINT
created_at          TIMESTAMP
```

---

## 9. Chat

### conversations

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
user_id             BIGINT FK → users.id INDEX
subject             VARCHAR(255)
status              ENUM('open','closed') DEFAULT 'open'
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### chat_messages

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
conversation_id     BIGINT FK → conversations.id INDEX
sender_id           BIGINT FK → users.id INDEX
body                TEXT
read_at             TIMESTAMP NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

- Messages are broadcast in real-time via Laravel Reverb to `conversation.{id}`
- `read_at` tracks when the recipient has seen the message

---

## 10. Idempotency

### idempotency_keys

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
key                 VARCHAR(255)
user_id             BIGINT FK → users.id
operation           VARCHAR(100)
fingerprint         VARCHAR(255)
status_code         INTEGER
response            JSON
expires_at          TIMESTAMP
created_at          TIMESTAMP
UNIQUE (key, user_id, operation)
```

**Why**

- Prevents duplicate orders and double-charges
- Fingerprint detects payload changes on retry (returns 409)
- Keys expire after 24 hours to prevent unbounded growth

---

## 11. Feature Flags & Configuration

### feature_flags

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
name                VARCHAR(255)
is_enabled          BOOLEAN DEFAULT false
description         TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### system_configs

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
key                 VARCHAR(255)
value               TEXT
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

## 12. Projections (Read Models)

### order_financial_projections

```sql
order_id            BIGINT PK FK → orders.id
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
total_amount        BIGINT
paid_amount         BIGINT
refunded_amount     BIGINT
refund_status       ENUM('none','partial','full')
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Why**

- Pre-calculated financial state avoids expensive aggregations
- Updated via domain event listeners on refund lifecycle events

---

### refund_projections

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
refund_id           BIGINT FK → refunds.id UNIQUE INDEX
order_id            BIGINT FK → orders.id INDEX
status              VARCHAR(50)
amount_cents        BIGINT
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

## 13. Observability

### domain_events

```sql
id                  BIGINT PK
event_type          VARCHAR(255) INDEX
payload             JSON
occurred_at         TIMESTAMP INDEX
processed_at        TIMESTAMP NULLABLE
created_at          TIMESTAMP
```

**Why**

- Full audit trail of all domain events
- Enables projection rebuilding via `php artisan projections:replay`
- Retention: 90 days

---

### audit_logs

```sql
id                  BIGINT PK
user_id             BIGINT FK → users.id NULLABLE INDEX
event               VARCHAR(255) INDEX
auditable_type      VARCHAR(255)
auditable_id        BIGINT
old_values          JSON NULLABLE
new_values          JSON NULLABLE
ip_address          VARCHAR(45) NULLABLE
user_agent          TEXT NULLABLE
created_at          TIMESTAMP
```

---

### metrics

```sql
id                  BIGINT PK
name                VARCHAR(255) INDEX
value               DOUBLE
labels              JSON NULLABLE
recorded_at         TIMESTAMP INDEX
created_at          TIMESTAMP
```

---

### alert_definitions

```sql
id                  BIGINT PK
name                VARCHAR(255)
metric_name         VARCHAR(255) INDEX
condition           ENUM('gt','lt','gte','lte','eq')
threshold           DOUBLE
window_minutes      INTEGER
notification_channels JSON
is_active           BOOLEAN DEFAULT true
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### alert_triggers

```sql
id                  BIGINT PK
alert_definition_id BIGINT FK → alert_definitions.id INDEX
triggered_at        TIMESTAMP
resolved_at         TIMESTAMP NULLABLE
metric_value        DOUBLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

## 14. Product Variants

### variant_attribute_types

```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id NULLABLE INDEX
name            VARCHAR(100)    -- e.g. "Size", "Colour"
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### variant_attribute_values

```sql
id                          BIGINT PK
variant_attribute_type_id   BIGINT FK → variant_attribute_types.id INDEX
value                       VARCHAR(100)    -- e.g. "Large", "Red"
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
```

### product_variants

```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id NULLABLE INDEX
product_id      BIGINT FK → products.id INDEX
sku             VARCHAR(100) NULLABLE
price_cents     BIGINT NULLABLE        -- overrides product price when set
is_active       BOOLEAN DEFAULT true
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Invariants**

- A variant belongs to exactly one product
- `sku` is unique per tenant when set
- `stocks` rows may reference a `variant_id` for variant-level inventory

### product_variant_attribute_values (pivot)

```sql
product_variant_id          BIGINT FK → product_variants.id INDEX
variant_attribute_value_id  BIGINT FK → variant_attribute_values.id INDEX
PRIMARY KEY (product_variant_id, variant_attribute_value_id)
```

---

## 15. Product Bundles

### bundles

```sql
id                          BIGINT PK
tenant_id                   BIGINT FK → tenants.id NULLABLE INDEX
name                        VARCHAR(255)
slug                        VARCHAR(255) UNIQUE INDEX
description                 TEXT NULLABLE
price_cents                 INT
compare_at_price_cents      INT NULLABLE
images                      JSON NULLABLE
is_active                   BOOLEAN DEFAULT true
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
```

**Notes**

- Bundle price is set at the bundle level and is not derived from the sum of its items
- `compare_at_price_cents` is the original/crossed-out price shown to communicate savings
- `images` is a JSON array of image URLs for the bundle

---

### bundle_items

```sql
id              BIGINT PK
bundle_id       BIGINT FK → bundles.id INDEX
product_id      BIGINT FK → products.id NULLABLE INDEX
variant_id      BIGINT FK → product_variants.id NULLABLE INDEX
quantity        INT DEFAULT 1
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Notes**

- Either `product_id` or `variant_id` should be set; `variant_id` takes precedence for variant-level items
- `quantity` is the number of that item included in the bundle

---

### cart_items / order_items — bundle_id column

`cart_items` and `order_items` each carry an optional `bundle_id FK → bundles.id`. When set, `product_id` is `NULL` and the item represents a whole bundle rather than an individual product. The checkout pipeline handles both paths without branching.

---

## 16. Subscriptions (Laravel Cashier)

### subscriptions

```sql
id              BIGINT PK
user_id         BIGINT FK → users.id INDEX
name            VARCHAR(255)
stripe_id       VARCHAR(255) UNIQUE INDEX
stripe_status   VARCHAR(255) INDEX
stripe_price    VARCHAR(255) NULLABLE
quantity        INT NULLABLE
trial_ends_at   TIMESTAMP NULLABLE
ends_at         TIMESTAMP NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Notes**

- Managed by Laravel Cashier; state is authoritative from Stripe webhooks — local rows are a cache
- `stripe_status` mirrors the Stripe subscription status (e.g., `active`, `trialing`, `canceled`)

---

### subscription_items

```sql
id                  BIGINT PK
subscription_id     BIGINT FK → subscriptions.id INDEX
stripe_id           VARCHAR(255) UNIQUE INDEX
stripe_product      VARCHAR(255) NULLABLE
stripe_price        VARCHAR(255) INDEX
quantity            INT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

---

### subscription_plans

```sql
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id NULLABLE INDEX
name                VARCHAR(255)
slug                VARCHAR(255) INDEX
stripe_price_id     VARCHAR(255) UNIQUE INDEX
price_cents         INT
interval            ENUM('monthly','yearly')
features            JSON NULLABLE
is_active           BOOLEAN DEFAULT true
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Notes**

- `stripe_price_id` links the plan to a Stripe Price object
- `features` is a JSON array of feature strings displayed on the plans page
- Plans are tenant-scoped; a `NULL` tenant_id indicates a platform-wide plan

---

## 15. User Addresses

### user_addresses

```sql
id          BIGINT PK
tenant_id   BIGINT FK → tenants.id NULLABLE INDEX
user_id     BIGINT FK → users.id INDEX
label       VARCHAR(100) NULLABLE    -- e.g. "Home", "Office"
line1       VARCHAR(255)
line2       VARCHAR(255) NULLABLE
city        VARCHAR(100)
state       VARCHAR(100) NULLABLE
postcode    VARCHAR(20) NULLABLE
country     CHAR(2)
is_default  BOOLEAN DEFAULT false
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

**Invariants**

- Each user has at most one `is_default = true` address per tenant
- Deleting the default address requires another address to be promoted first

---

## 16. Review Enhancements

### review_photos

```sql
id          BIGINT PK
review_id   BIGINT FK → reviews.id INDEX
path        VARCHAR(255)
disk        VARCHAR(50) DEFAULT 'public'
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

### review_replies

```sql
id          BIGINT PK
review_id   BIGINT FK → reviews.id INDEX
user_id     BIGINT FK → users.id INDEX
body        TEXT
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

### review_votes

```sql
id          BIGINT PK
review_id   BIGINT FK → reviews.id INDEX
user_id     BIGINT FK → users.id INDEX
is_helpful  BOOLEAN
UNIQUE (review_id, user_id)
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

---

## 17. Indexing Strategy

Recommended composite indexes:

```sql
orders (user_id, created_at)
orders (tenant_id, status)
stock_movements (product_id, created_at)
payment_intents (provider_reference)
idempotency_keys (key, user_id, operation)
chat_messages (conversation_id, created_at)
reviews (product_id, created_at)
domain_events (event_type, occurred_at)
metrics (name, recorded_at)
product_variants (product_id, is_active)
user_addresses (user_id, is_default)
```

---

## 18. Things Intentionally NOT in the Database

- Calculated totals (derived at order creation, snapshotted)
- Cached catalog responses (stored in Redis via `currency_rates_{base}` cache keys)
- Session state
- Real-time presence data

These belong to **cache, sessions, or application layers**, not persistence.
