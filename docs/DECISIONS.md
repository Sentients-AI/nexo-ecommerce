# Architectural Decision Log

Decisions are recorded chronologically. Each entry describes the decision, the reason, trade-offs accepted, and consequences.

> For deep-dive explanations with code examples and diagrams, see [ARCHITECTURE_DECISIONS.md](ARCHITECTURE_DECISIONS.md).

---

## 2026-01-03 — Domain-driven folder boundaries

**Decision**
Organised the codebase around domain boundaries (Product, Inventory, Cart, Order, Payment) rather than technical layers alone.

**Reason**
Business rules evolve faster than frameworks. Domain boundaries reduce coupling and make reasoning about invariants easier.

**Trade-offs**
- Slightly more upfront structure
- Higher cognitive load for beginners

**Consequences**
- Easier refactoring
- Clear ownership of business rules
- Scales better as complexity grows

---

## 2026-01-03 — Monetary values stored as integers

**Decision**
All monetary values are stored as integers representing the smallest currency unit (e.g., cents).

**Reason**
Avoids floating-point precision errors in calculations and comparisons.

**Trade-offs**
- Requires formatting at the presentation layer

**Consequences**
- Financial correctness guaranteed
- Safer calculations inside database transactions

---

## 2026-01-03 — Inventory managed via stock movements

**Decision**
Inventory is tracked using a `stock_movements` table instead of directly mutating stock counts.

**Reason**
Allows auditing, debugging, and reconciliation of stock changes over time.

**Trade-offs**
- Slightly more complex queries

**Consequences**
- Full traceability of every stock change
- Easier to debug discrepancies

---

## 2026-01-03 — Cart implemented as a price snapshot

**Decision**
Cart items store product price and tax at the time they are added.

**Reason**
Prices and taxes can change; orders must reflect what the customer saw at the time of adding to cart.

**Trade-offs**
- Data duplication between cart items and products

**Consequences**
- Correct historical orders
- Fewer edge-case price disputes

---

## 2026-01-03 — Payment handled asynchronously

**Decision**
Payment confirmation relies on Stripe webhooks and background jobs rather than synchronous API responses.

**Reason**
External payment systems are unreliable; async processing improves resilience and prevents timeout-induced double-charges.

**Trade-offs**
- More complex flow (pending state required in UI)

**Consequences**
- Fault tolerance on network failures
- Better scalability under load

---

## 2026-01-22 — Specification pattern for business rules

**Decision**
Business rule validation is encapsulated in Specification classes that can be composed using AND/OR/NOT logic.

**Reason**
Complex validation logic was scattered across Actions, Models, and Enums. Specifications make rules explicit, testable, and reusable.

**Trade-offs**
- Additional abstraction layer
- More files to maintain

**Consequences**
- Clear separation of business rules from orchestration
- Composable validation (e.g., `OrderIsRefundable AND RefundAmountIsValid`)
- Consistent error messages via `getFailureReason()`
- Easier to test individual rules in isolation

---

## 2026-01-22 — Identity Value Objects for type safety

**Decision**
Aggregate root IDs are wrapped in typed Value Objects (e.g., `OrderId`, `UserId`, `CartId`).

**Reason**
Scalar IDs (`int`) can be accidentally swapped between different entity types. Value Objects prevent passing an `order_id` where a `user_id` is expected.

**Trade-offs**
- Verbose factory calls (`OrderId::fromInt($id)`)
- Requires `->toInt()` for database queries

**Consequences**
- Compile-time type safety
- Self-documenting code
- Prevents ID confusion bugs

---

## 2026-01-22 — Application layer Use Cases for orchestration

**Decision**
Complex multi-step operations are orchestrated by Use Case classes in the Application layer, separate from Domain Actions.

**Reason**
Domain Actions should focus on single responsibilities. Use Cases coordinate multiple Actions and apply Specifications before execution.

**Trade-offs**
- Additional layer between HTTP and Domain
- More indirection

**Consequences**
- Clear entry points for complex operations
- Specifications applied at orchestration level
- Request/Response DTOs with Value Object IDs
- Easier to test orchestration logic in isolation

---

## 2026-01-28 — Guard pattern for enforcing domain invariants

**Decision**
Domain invariants are enforced via Guard classes that implement a shared `Guard` interface with `check()`, `getViolationMessage()`, and `enforce()` methods.

**Reason**
Invariants were scattered across Actions, Models, and Specifications. Guards centralise enforcement, provide consistent error handling, and enable monitoring of violation attempts.

**Trade-offs**
- Additional abstraction layer alongside Specifications
- More files to maintain

**Consequences**
- Invariant violations are logged to the security channel via `InvariantViolationAttempted` event
- Guards are reusable across multiple Actions
- Failed checks throw `DomainException` with consistent messages
- Violations can be monitored and alerted on

---

## 2026-01-28 — Domain event recording for event sourcing foundation

**Decision**
All domain events are recorded in a `domain_events` table with serialized payload, enabling event replay and projection rebuilding.

**Reason**
Event sourcing provides audit trails, enables projection rebuilding, and supports debugging of state changes over time.

**Trade-offs**
- Storage overhead for event payloads
- Requires careful serialization/deserialization

**Consequences**
- Projections can be rebuilt by replaying events
- Full audit trail of all state changes
- Supports debugging production issues by replaying events

---

## 2026-01-31 — Pluggable metrics driver pattern

**Decision**
Metrics collection uses a `MetricsDriver` interface with `DatabaseMetricsDriver` as the default implementation, recording to a `metrics` table.

**Reason**
Different environments may need different metrics backends (database for dev, Prometheus/Datadog for prod). A driver pattern enables flexibility without code changes.

**Trade-offs**
- Indirection via static `MetricsRecorder` facade
- Database driver may not scale for high-volume metrics

**Consequences**
- Request duration, budget violations, and domain events are tracked
- Metrics can be queried for alerting with time windows and label filtering
- Easy to swap to external metrics backend when needed

---

## 2026-02-01 — Alerting system with configurable thresholds

**Decision**
Alert definitions are stored in the database with metric name, condition, threshold, and notification channels. An `EvaluateAlertsAction` checks conditions and creates/resolves triggers.

**Reason**
Alert thresholds need to be adjustable without code deployment. Database storage enables runtime configuration via admin panel.

**Trade-offs**
- Requires scheduled evaluation command
- More complex than hardcoded thresholds

**Consequences**
- Alerts can be created, modified, and disabled without deployment
- Alert triggers track active/resolved state with timestamps
- Supports multiple notification channels per alert

---

## 2026-02-01 — Retry and compensation actions for resilience

**Decision**
Operations that may fail have dedicated retry actions (`RetryOrderFinalizationAction`, `RetryRefundExecutionAction`) and compensation actions (`CompensateStockOnCancelAction`).

**Reason**
Distributed systems fail; dedicated retry/compensation logic ensures eventual consistency and provides clear recovery paths.

**Trade-offs**
- More code to maintain
- Must be careful about idempotency in retry logic

**Consequences**
- Stuck operations can be recovered via console commands
- Stock reservations are properly released on order cancellation
- Admin can retry failed refunds without manual database manipulation

---

## 2026-02-01 — Correlation IDs for distributed tracing

**Decision**
Every request receives a `X-Correlation-ID` header (generated if not provided) that is propagated through logs, errors, and responses.

**Reason**
Debugging production issues requires linking logs, metrics, and errors across the request lifecycle.

**Trade-offs**
- Header overhead on every request
- Requires consistent propagation

**Consequences**
- All error responses include correlation ID
- Logs can be filtered by correlation ID
- Support can trace customer issues end-to-end

---

## 2026-02-01 — Standardized API error response format

**Decision**
All API errors follow a consistent schema with `code`, `message`, `retryable`, and optional `correlation_id` and `details` fields.

**Reason**
Clients need predictable error handling. Structured errors enable automatic retry logic and better user feedback.

**Trade-offs**
- Must map all exceptions to error codes
- Slightly larger error payloads

**Consequences**
- Frontend can implement automatic retry for retryable errors
- Error codes are documented and stable
- Debug details only shown in development mode

---

## 2026-02-01 — Performance budgets per route

**Decision**
Each route category has a maximum response time budget (e.g., checkout: 3000ms, order list: 500ms). Budget violations are logged and tracked as metrics.

**Reason**
Performance regressions need early detection. Budgets make expectations explicit and measurable.

**Trade-offs**
- Budgets may need tuning over time
- Adds middleware overhead (minimal)

**Consequences**
- Slow endpoints are immediately visible in metrics
- Can alert on budget violation rate increases
- Response time header (`X-Response-Time`) aids debugging

---

## 2026-02-03 — API versioning with v1 prefix

**Decision**
All public API endpoints are versioned under `/api/v1/` prefix with dedicated route files (`routes/api/v1.php`) and namespaced controllers (`App\Http\Controllers\Api\V1`).

**Reason**
API contracts must remain stable for clients. Versioning allows breaking changes in future versions without disrupting existing integrations.

**Trade-offs**
- Additional directory structure
- Must maintain multiple versions if v2 is introduced

**Consequences**
- Clear separation between API versions
- Controllers and resources are version-specific
- Frontend can target a specific API version

---

## 2026-02-03 — Centralized error codes via ErrorCode enum

**Decision**
All API errors use a centralized `ErrorCode` enum that maps to HTTP status codes, user-safe messages, and retry hints.

**Reason**
Scattered error handling led to inconsistent client experiences. Centralized codes enable automatic retry logic and predictable error handling.

**Trade-offs**
- Must add new codes for new error scenarios
- Requires mapping domain exceptions to error codes

**Consequences**
- `ErrorCode::isRetryable()` enables automatic client retries
- `ErrorMessages` class separates user-safe from technical messages
- `TransformDomainExceptions` middleware handles mapping consistently

---

## 2026-02-03 — Session-based anonymous carts

**Decision**
Carts work for both authenticated users (via `user_id`) and anonymous sessions (via Laravel session). Cart is transferred to the user on authentication.

**Reason**
E-commerce requires guest checkout. Users should not lose their cart when they decide to log in.

**Trade-offs**
- More complex cart lookup logic
- Session storage considerations for scaling

**Consequences**
- Guest users can add items to cart before registering
- Cart persists across page reloads via session
- Login merges or transfers cart to authenticated user

---

## 2026-02-07 — Shared database multi-tenancy with tenant_id column

**Decision**
Multi-tenancy is implemented using a shared database with a nullable `tenant_id` column on all tenant-scoped tables. Tenant isolation is enforced via a `TenantScope` global scope applied through the `BelongsToTenant` trait.

**Reason**
Shared database is simpler to operate than database-per-tenant. A global scope ensures data isolation without requiring explicit filtering in every query.

**Trade-offs**
- All queries must respect tenant scope (enforced by global scope)
- Cross-tenant queries require explicit `withoutTenancy()` call
- Nullable `tenant_id` allows super admins to exist outside any tenant
- Composite unique indexes needed for fields like SKU (unique per tenant, not globally)

**Consequences**
- Automatic tenant filtering on all model queries
- Super admins can impersonate tenants via session-based tenant switcher
- Queue jobs propagate tenant context automatically via Laravel Context
- Factories respect tenant context or create a new tenant if none is set
- 21 models are tenant-scoped; pivot tables and roles are shared

---

## 2026-02-07 — Subdomain-based tenant identification

**Decision**
Tenants are identified by subdomain (e.g., `acme-store.yourdomain.com`). The `ResolveTenantFromSubdomain` middleware resolves the tenant and sets it in Laravel's Context facade.

**Reason**
Subdomains provide clear tenant separation in URLs and are familiar to users. The Context facade allows the tenant to propagate across the request lifecycle including queued jobs.

**Trade-offs**
- Requires wildcard DNS configuration
- Local development needs hosts file entries or special handling
- Reserved subdomains (www, api, admin) must be blocked

**Consequences**
- Each tenant has a unique, branded URL
- Tenant context is available throughout request via `Context::get('tenant_id')`
- API requests resolve tenant from authenticated user instead of subdomain

---

## 2026-02-12 — Product reviews with per-user uniqueness

**Decision**
Product reviews are stored per user per product with a unique constraint. Reviews include a 1–5 star rating and an optional text body.

**Reason**
Reviews provide social proof and product feedback. The unique constraint prevents spam and ensures one meaningful review per customer.

**Trade-offs**
- Customers cannot update their review after submission (requires explicit edit flow)
- Review moderation not yet implemented

**Consequences**
- `products.average_rating` and `review_count` are computed via Eloquent aggregates
- Reviews are tenant-scoped, so each store has independent reviews

---

## 2026-02-20 — Real-time chat via Laravel Reverb

**Decision**
Customer support conversations are implemented using Laravel Reverb (WebSockets). Messages are broadcast to private `conversation.{id}` channels after persistence.

**Reason**
Support staff and customers need immediate feedback without polling. Reverb is the first-party WebSocket server for Laravel, requiring no external service.

**Trade-offs**
- Reverb server must be running alongside PHP workers
- Message delivery relies on WebSocket connection availability (fallback to polling)

**Consequences**
- Messages appear instantly in both customer and admin views
- Conversations are tenant-scoped with full message history persisted
- Chat Resource in Filament admin allows support staff to reply to all tenant conversations

---

## 2026-02-20 — Internationalization via URL locale prefix

**Decision**
The frontend supports multiple locales via URL prefix (`/en/`, `/ar/`, `/ms/`). A `locale` middleware sets the application locale from the URL segment. The root `/` redirects to `/en/`.

**Reason**
URL-based locale is bookmarkable, shareable, and SEO-friendly. It avoids session-based locale state that breaks across devices.

**Trade-offs**
- All named routes must include the locale parameter
- Arabic RTL layout requires `dir="rtl"` on the document root

**Consequences**
- Three supported locales: English, Arabic, Malay
- Translation strings live in `lang/{locale}/` directories
- Laravel's `__()` helper and Vue i18n work off the same locale

---

## 2026-03-20 — Loyalty points stored as an append-only ledger

**Decision**
Loyalty points are tracked via two tables: `loyalty_accounts` (current balance snapshot) and `loyalty_transactions` (immutable ledger of every change). Balances are never recalculated from the ledger at query time.

**Reason**
An append-only ledger is auditable, debuggable, and safe for concurrent writes. Snapshotting the balance on the account avoids expensive full-ledger aggregations on every page load. The pattern mirrors how financial systems (double-entry bookkeeping) work.

**Trade-offs**
- Balance can theoretically drift from the ledger if a write partially fails — mitigated by wrapping all mutations in DB transactions
- Two writes per point event instead of one

**Consequences**
- `AwardPointsAction` and `RedeemPointsAction` always run inside a transaction
- `RedeemPointsAction` uses `lockForUpdate` to prevent over-redemption under concurrency
- All redemptions require minimum 100 points (configurable in `config/loyalty.php`)

---

## 2026-03-20 — Referral codes as tenant-scoped, time-limited, capped tokens

**Decision**
Each user gets one active referral code per tenant. Codes are 12-character uppercase alphanumeric strings. They support optional `expires_at` and `max_uses` limits. Applying a code is atomic — increment, award points, and record usage all happen in a single DB transaction.

**Reason**
Time and usage limits prevent long-lived abuse (e.g., a code shared publicly to thousands of strangers). Atomicity prevents split-brain between point award and usage record.

**Trade-offs**
- `GenerateReferralCodeAction` is idempotent (returns existing active code) — new expiry/limits can only be set via `regenerate`
- Discount is recorded as a coupon code string; it is not yet wired to the Promotion engine

**Consequences**
- `SelfReferralException` and `ReferralAlreadyUsedException` are enforced at the action layer, not just the DB constraint
- Future work: wire `referee_coupon_code` to a real Promotion record

---

## 2026-03-20 — Full-text search via Typesense + Laravel Scout

**Decision**
Replace `LIKE '%query%'` searches with Typesense via Laravel Scout. Product, Category, and Order models implement `Searchable`. The web `ProductController` and the new API `SearchController` both use Scout when a `q` parameter is present.

**Reason**
`LIKE '%query%'` scans every row and cannot do typo tolerance, relevance ranking, or faceted filtering. Typesense provides sub-50ms search, typo correction, and scales independently of the primary database.

**Trade-offs**
- Requires running a Typesense server (self-hosted or cloud)
- Search index can lag behind the database by a queue processing delay (mitigated by `queue = true` in `config/scout.php`)
- Typesense bypasses Eloquent's global scopes — every Scout call must explicitly pass `.where('tenant_id', ...)` for tenant isolation

**Consequences**
- Tests use `SCOUT_DRIVER=collection` (in-memory) — no real Typesense needed in CI
- After deploy, run `php artisan scout:import` for each model to seed the index
- All three searchable models include `tenant_id` in `toSearchableArray()` for safe filtering

---

## 2026-03-25 — Product variants as a first-class entity

**Decision**
Add a `ProductVariant` model with a polymorphic attribute system (`VariantAttributeType` / `VariantAttributeValue`). Variants carry optional `sku` and `price_cents` overrides and link to their own `Stock` rows via a `variant_id` column.

**Reason**
Products such as clothing or electronics have size/colour/material combinations that need independent pricing and inventory. A flat product model cannot represent these without data duplication.

**Trade-offs**
- Cart and order items now optionally reference a `variant_id`, so any code reading `CartItem` or `OrderItem` must handle the nullable variant
- Stocks table relaxed its unique constraint from `(product_id)` to `(product_id, variant_id)` — existing single-variant products keep the old behaviour

**Consequences**
- `lockAndValidateStock` in `CreateOrderFromCart` must filter by `variant_id` when present
- Filament admin panel has a `ProductVariantsRelationManager` for inline variant management

---

## 2026-03-26 — User address book (multiple shipping addresses)

**Decision**
Add a `user_addresses` table with `label`, full address fields, and an `is_default` flag. Four actions (`CreateAddress`, `UpdateAddress`, `DeleteAddress`, `SetDefaultAddress`) enforce the single-default invariant atomically.

**Reason**
Customers need to save multiple delivery addresses (home, office, etc.) and designate one as the default to pre-fill checkout.

**Trade-offs**
- Orders now optionally store a `shipping_address_id` FK — historical orders without an address are unaffected
- The default-address invariant (`at most one is_default = true per user`) is enforced in application code, not a DB partial unique index, to stay compatible with MySQL 5.7

**Consequences**
- `SetDefaultAddress` action wraps in a transaction: clears all `is_default`, then sets the target
- Address deletion is rejected if the address is the user's only default (front-end must prompt the user to set a new default first)

---

## 2026-03-26 — Abandoned cart recovery via scheduled emails

**Decision**
A `SendAbandonedCartRecoveryCommand` scheduled daily identifies carts idle ≥ 24 h that have not yet received a recovery email, then dispatches `AbandonedCartRecovery` notifications and stamps `recovery_email_sent_at`.

**Reason**
Abandoned carts represent lost revenue. A single well-timed recovery email recovers a meaningful fraction without requiring a third-party tool.

**Trade-offs**
- Only one recovery email per cart — no drip sequence — to avoid spam complaints
- Does not personalise with product images (plain text + product names only) to keep deliverability high

**Consequences**
- `carts` table gains `recovery_email_sent_at TIMESTAMP NULLABLE`
- The command is idempotent: re-running it on the same day sends no duplicate emails

---

## 2026-03-27 — Multi-currency support with live exchange rates

**Decision**
Add a `CurrencyService` backed by the free Frankfurter.app API (ECB data, no API key). Rates are cached per base currency for one hour. At checkout the order stores the converted amounts **plus** the original `base_total_cents` and `exchange_rate` for a full audit trail.

**Reason**
The platform is multi-tenant; different tenants operate in different countries and currencies. Buyers in a USD store should not see MYR prices. Locking the exchange rate at checkout prevents revenue discrepancies if the rate moves between order and payment confirmation.

**Trade-offs**
- Frankfurter.app is a free, unguaranteed service; the service falls back to rate `1.0` on failure rather than blocking checkout
- Rates are cached 1 hour, so intra-hour fluctuations are not reflected
- Supported currencies are an allow-list (`config/currency.supported`) — adding a new one is a one-line config change

**Consequences**
- `orders` table gains `base_currency CHAR(3)`, `exchange_rate DECIMAL(10,6)`, `base_total_cents BIGINT`
- `FetchExchangeRatesCommand` (`currency:fetch-rates`) runs hourly via the scheduler to warm the cache proactively
- All Vue components use the shared `useCurrency` composable (Inertia prop `currency`) instead of hardcoded USD formatting
- `CheckoutRequest` validates `currency` against `config('currency.supported')`

---

## 2026-03-27 — Notification Center with real-time delivery

**Decision**
Use Laravel's built-in `DatabaseNotification` model (UUID PK) for persistence and broadcast notifications over Reverb for real-time badge updates. A `NotificationController` handles CRUD; an `unread_notifications_count` Inertia shared prop keeps the UI badge live.

**Reason**
Customers need in-app awareness of order status changes, refund decisions, and loyalty milestones without refreshing the page.

**Trade-offs**
- `RouteDependencyResolverTrait` in Laravel injects route parameters positionally, which conflicts with locale-prefixed routes — `markRead` and `destroy` use `$request->route('id')` directly instead of a `string $id` parameter

**Consequences**
- The `unread_notifications_count` shared prop is lazy (`fn ()`) so it adds zero overhead on pages that don't render the badge
- Notification types are plain PHP classes with a `toArray()` for storage and `toBroadcast()` for WebSocket push


---

## 2026-03-31 — Guest checkout without mandatory registration

**Decision**
Allow unauthenticated users to complete checkout by passing `guest_email` and `guest_name` in the checkout request. Account creation is offered (but not required) post-purchase.

**Reason**
Mandatory registration is a leading cause of cart abandonment. Guest checkout lowers conversion friction while still capturing order data.

**Trade-offs**
- Guest orders have no user_id; refund / reorder flows require email-based lookup
- Cannot apply loyalty points or referral codes without an authenticated user

**Consequences**
- `POST /api/v1/checkout` no longer requires `auth:sanctum`
- `CreateOrderFromCart` handles nullable `user_id`

---

## 2026-03-31 — Tenant self-service onboarding at /start

**Decision**
A public wizard at `/start` lets new vendors register: pick a subdomain, name the store, create the admin account. `CreateTenantWithAdminUser` action handles tenant creation + user creation + role assignment + default settings atomically.

**Reason**
Previously tenants could only be created by super-admins in the Filament control plane, blocking self-serve growth.

**Trade-offs**
- The slug uniqueness check has a TOCTOU window between validation and creation; a unique DB constraint is the true guard
- `reserved_subdomains` list in `config/tenancy.php` must be kept up to date

**Consequences**
- New tenants start on a 14-day trial (`trial_ends_at = now()->addDays(14)`)
- `OnboardingRequest` validates slug format (lowercase alphanumeric + hyphens, 3–63 chars), uniqueness, and reserved-word check

---

## 2026-03-31 — Back-in-stock waitlist via domain event

**Decision**
`WaitlistSubscription` stores email + product_id. A `StockReplenished` domain event fires when stock is restocked; a listener queries pending subscriptions, sends `BackInStockNotification`, and stamps `notified_at`.

**Reason**
Re-engagement without polling. Event-driven approach keeps the inventory domain decoupled from the notification concern.

**Trade-offs**
- Listener runs synchronously in the default queue worker; for very large waitlists this should be chunked

**Consequences**
- `waitlist_subscriptions` table added (tenant-scoped)
- `POST /api/v1/products/{slug}/waitlist` is public (no auth required)

---

## 2026-03-31 — Sitemap and product SEO meta

**Decision**
`SitemapController` generates a `<urlset>` XML sitemap covering home pages, active products, and category filter URLs for all supported locales (en/ar/ms). `ProductController::show()` passes a `seo` prop; `Products/Show.vue` renders `<Head>` with meta description, Open Graph, Twitter Card, and JSON-LD Product structured data.

**Reason**
Search engine discoverability is a hard requirement for any public storefront. Centralising SEO data in the controller keeps Vue components free of business logic.

**Trade-offs**
- Sitemap is generated on every request (no caching beyond HTTP `Cache-Control: public, max-age=3600`)
- JSON-LD price uses the stored cents value ÷ 100; does not reflect real-time currency conversion

**Consequences**
- `/sitemap.xml` is tenant-scoped (uses global tenant context, no auth required)
- `seo.canonical_url` prevents duplicate-content penalties from locale variants

---

## 2026-04-09 — Product bundles as first-class domain objects

**Decision**
Bundles are modelled as their own domain entity (`Bundle` + `BundleItem`) with nullable `product_id` on cart items rather than using promotions or product variants.

**Reason**
Bundles have their own SKU, price, and discount logic distinct from variant pricing and promotional discounts. First-class modelling keeps the checkout pipeline clean.

**Trade-offs**
- Extra join when cart items can be either a product or a bundle.

**Consequences**
- Cart and order items carry an optional `bundle_id`; the checkout handles both paths without branching.

---

## 2026-04-09 — Subscription billing via Laravel Cashier

**Decision**
Recurring subscriptions use Laravel Cashier (Stripe Billing) rather than a custom billing engine.

**Reason**
Cashier handles webhook sync, trial periods, proration, and the Stripe Customer Portal out of the box.

**Trade-offs**
- Tighter coupling to Stripe; switching payment providers requires replacing Cashier.

**Consequences**
- Subscription state is always authoritative from Stripe webhooks; local state is a cache.

---

## 2026-04-10 — CSS-first Filament theme customisation

**Decision**
Admin panel theming uses a custom CSS file (`resources/css/filament/admin.css`) with CSS custom properties rather than PHP theme configuration.

**Reason**
Filament v5 recommends CSS-first approach; easier to maintain without PHP compilation.

**Trade-offs**
- Changes require a CSS build step.

**Consequences**
- Electric Indigo (`#6747f5`) brand colour is applied consistently across the admin panel.
