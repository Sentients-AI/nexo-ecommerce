2026-01-03 — Chose domain-driven folder boundaries

Decision
Organised the codebase around domain boundaries (Product, Inventory, Cart, Order, Payment) rather than technical layers alone.

Reason
Business rules evolve faster than frameworks. Domain boundaries reduce coupling and make reasoning about invariants easier.

Trade-offs

Slightly more upfront structure

Higher cognitive load for beginners

Consequences

Easier refactoring

Clear ownership of business rules

Scales better as complexity grows

2026-01-03 — Monetary values stored as integers

Decision
All monetary values are stored as integers representing the smallest currency unit (e.g. cents).

Reason
Avoids floating-point precision errors in calculations and comparisons.

Trade-offs

Requires formatting at the presentation layer

Consequences

Financial correctness

Safer calculations in transactions

2026-01-03 — Inventory managed via stock movements

Decision
Inventory is tracked using a stock_movements table instead of directly mutating stock counts.

Reason
Allows auditing, debugging, and reconciliation of stock changes over time.

Trade-offs

Slightly more complex queries

Consequences

Full traceability

Easier to debug discrepancies

2026-01-03 — Cart implemented as a price snapshot

Decision
Cart items store product price and tax at the time they are added.

Reason
Prices and taxes can change; orders must reflect what the customer saw.

Trade-offs

Data duplication

Consequences

Correct historical orders

Fewer edge-case disputes

2026-01-03 — Payment handled asynchronously

Decision
Payment confirmation relies on webhooks and background jobs rather than synchronous responses.

Reason
External systems are unreliable; async processing improves resilience.

Trade-offs

More complex flow

Consequences

Fault tolerance

Better scalability

2026-01-22 — Specification pattern for business rules

Decision
Business rule validation is encapsulated in Specification classes that can be composed using AND/OR/NOT logic.

Reason
Complex validation logic was scattered across Actions, Models, and Enums. Specifications make rules explicit, testable, and reusable.

Trade-offs

Additional abstraction layer

More files to maintain

Consequences

Clear separation of business rules from orchestration

Composable validation (e.g., `OrderIsRefundable AND RefundAmountIsValid`)

Consistent error messages via `getFailureReason()`

Easier to test individual rules in isolation

2026-01-22 — Identity Value Objects for type safety

Decision
Aggregate root IDs are wrapped in typed Value Objects (e.g., `OrderId`, `UserId`, `CartId`).

Reason
Scalar IDs (int) can be accidentally swapped between different entity types. Value Objects prevent passing an `order_id` where a `user_id` is expected.

Trade-offs

Verbose factory calls (`OrderId::fromInt($id)`)

Requires `->toInt()` for database queries

Consequences

Compile-time type safety

Self-documenting code

Prevents ID confusion bugs

2026-01-22 — Application layer Use Cases for orchestration

Decision
Complex multi-step operations are orchestrated by Use Case classes in the Application layer, separate from Domain Actions.

Reason
Domain Actions should focus on single responsibilities. Use Cases coordinate multiple Actions and apply Specifications before execution.

Trade-offs

Additional layer between HTTP and Domain

More indirection

Consequences

Clear entry points for complex operations

Specifications applied at orchestration level

Request/Response DTOs with Value Object IDs

Easier to test orchestration logic

2026-01-28 — Guard pattern for enforcing domain invariants

Decision
Domain invariants are enforced via Guard classes that implement a shared `Guard` interface with `check()`, `getViolationMessage()`, and `enforce()` methods.

Reason
Invariants were scattered across Actions, Models, and Specifications. Guards centralise enforcement, provide consistent error handling, and enable monitoring of violation attempts.

Trade-offs

Additional abstraction layer alongside Specifications

More files to maintain

Consequences

Invariant violations are logged to the security channel via `InvariantViolationAttempted` event

Guards are reusable across multiple Actions

Failed checks throw `DomainException` with consistent messages

Violations can be monitored and alerted on

2026-01-28 — Domain event recording for event sourcing foundation

Decision
All domain events are recorded in a `domain_events` table with serialized payload, enabling event replay and projection rebuilding.

Reason
Event sourcing provides audit trails, enables projection rebuilding, and supports debugging of state changes over time.

Trade-offs

Storage overhead for event payloads

Requires careful serialization/deserialization

Consequences

Projections can be rebuilt by replaying events

Full audit trail of all state changes

Supports debugging production issues by replaying events

2026-01-31 — Pluggable metrics driver pattern

Decision
Metrics collection uses a `MetricsDriver` interface with `DatabaseMetricsDriver` as the default implementation, recording to a `metrics` table.

Reason
Different environments may need different metrics backends (database for dev, Prometheus/Datadog for prod). A driver pattern enables flexibility without code changes.

Trade-offs

Indirection via static `MetricsRecorder` facade

Database driver may not scale for high-volume metrics

Consequences

Request duration, budget violations, and domain events are tracked

Metrics can be queried for alerting with time windows and label filtering

Easy to swap to external metrics backend when needed

2026-02-01 — Alerting system with configurable thresholds

Decision
Alert definitions are stored in the database with metric name, condition, threshold, and notification channels. An `EvaluateAlertsAction` checks conditions and creates/resolves triggers.

Reason
Alert thresholds need to be adjustable without code deployment. Database storage enables runtime configuration via admin panel.

Trade-offs

Requires scheduled evaluation command

More complex than hardcoded thresholds

Consequences

Alerts can be created, modified, and disabled without deployment

Alert triggers track active/resolved state with timestamps

Supports multiple notification channels per alert

2026-02-01 — Retry and compensation actions for resilience

Decision
Operations that may fail have dedicated retry actions (`RetryOrderFinalizationAction`, `RetryRefundExecutionAction`) and compensation actions (`CompensateStockOnCancelAction`).

Reason
Distributed systems fail; dedicated retry/compensation logic ensures eventual consistency and provides clear recovery paths.

Trade-offs

More code to maintain

Must be careful about idempotency

Consequences

Stuck operations can be recovered via console commands

Stock reservations are properly released on order cancellation

Admin can retry failed refunds without manual database manipulation

2026-02-01 — Correlation IDs for distributed tracing

Decision
Every request receives a `X-Correlation-ID` header (generated if not provided) that is propagated through logs, errors, and responses.

Reason
Debugging production issues requires linking logs, metrics, and errors across the request lifecycle.

Trade-offs

Header overhead on every request

Requires consistent propagation

Consequences

All error responses include correlation ID

Logs can be filtered by correlation ID

Support can trace customer issues end-to-end

2026-02-01 — Standardized API error response format

Decision
All API errors follow a consistent schema with `code`, `message`, `retryable`, and optional `correlation_id` and `details` fields.

Reason
Clients need predictable error handling. Structured errors enable automatic retry logic and better user feedback.

Trade-offs

Must map all exceptions to error codes

Slightly larger error payloads

Consequences

Frontend can implement automatic retry for retryable errors

Error codes are documented and stable

Debug details only shown in development mode

2026-02-01 — Performance budgets per route

Decision
Each route category has a maximum response time budget (e.g., checkout: 3000ms, order list: 500ms). Budget violations are logged and tracked as metrics.

Reason
Performance regressions need early detection. Budgets make expectations explicit and measurable.

Trade-offs

Budgets may need tuning over time

Adds middleware overhead (minimal)

Consequences

Slow endpoints are immediately visible in metrics

Can alert on budget violation rate increases

Response time header (`X-Response-Time`) aids debugging

2026-02-03 — API versioning with v1 prefix

Decision
All public API endpoints are versioned under `/api/v1/` prefix with dedicated route files (`routes/api/v1.php`) and namespaced controllers (`App\Http\Controllers\Api\V1`).

Reason
API contracts must remain stable for clients. Versioning allows breaking changes in future versions without disrupting existing integrations.

Trade-offs

Additional directory structure

Must maintain multiple versions if v2 is introduced

Consequences

Clear separation between API versions

Controllers and resources are version-specific

Frontend can target specific API version

2026-02-03 — Centralized error codes via ErrorCode enum

Decision
All API errors use a centralized `ErrorCode` enum that maps to HTTP status codes, user-safe messages, and retry hints.

Reason
Scattered error handling led to inconsistent client experiences. Centralized codes enable automatic retry logic and predictable error handling.

Trade-offs

Must add new codes for new error scenarios

Requires mapping domain exceptions to error codes

Consequences

`ErrorCode::isRetryable()` enables automatic client retries

`ErrorMessages` class separates user-safe from technical messages

`TransformDomainExceptions` middleware handles mapping consistently

2026-02-03 — Session-based anonymous carts

Decision
Carts work for both authenticated users (via `user_id`) and anonymous sessions (via Laravel session). Cart is transferred to user on authentication.

Reason
E-commerce requires guest checkout. Users should not lose their cart when they decide to log in.

Trade-offs

More complex cart lookup logic

Session storage considerations for scaling

Consequences

Guest users can add items to cart before registering

Cart persists across page reloads via session

Login merges or transfers cart to authenticated user
