# API Documentation — E-Commerce System

All endpoints return JSON. API version 1 is available at `/api/v1/`.

---

## Multi-Tenancy

API requests are tenant-scoped based on the authenticated user's tenant. All data returned is automatically filtered to the user's tenant.

**Tenant Resolution**
- **Web/Storefront**: Resolved from subdomain (e.g., `acme-store.yourdomain.com`)
- **API**: Resolved from the authenticated user's `tenant_id`

**Super Admin Access**
Super admins (users with `tenant_id = NULL`) can access data across all tenants through the Control Plane admin interface.

---

## Error Response Format

All errors follow a standardized schema:

```json
{
  "error": {
    "code": "CART_EMPTY",
    "message": "Your cart is empty",
    "retryable": false,
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Error Codes

| Code | HTTP Status | Retryable | Description |
|------|-------------|-----------|-------------|
| `CART_EMPTY` | 422 | No | Cart has no items |
| `CART_ALREADY_COMPLETED` | 422 | No | Cart already checked out |
| `INSUFFICIENT_STOCK` | 422 | No | Not enough stock available |
| `ORDER_NOT_FOUND` | 404 | No | Order does not exist |
| `ORDER_NOT_PAID` | 422 | No | Order has not been paid |
| `ORDER_NOT_REFUNDABLE` | 422 | No | Order cannot be refunded |
| `PAYMENT_FAILED` | 422 | Yes | Payment processing failed |
| `PAYMENT_ALREADY_CONFIRMED` | 422 | No | Payment already processed |
| `REFUND_EXCEEDS_TOTAL` | 422 | No | Refund amount exceeds order total |
| `INVALID_IDEMPOTENCY_KEY` | 409 | No | Idempotency key conflict |
| `LOYALTY_ACCOUNT_NOT_FOUND` | 404 | No | No loyalty account for this user |
| `INSUFFICIENT_POINTS` | 400 | No | Not enough points to redeem |
| `BELOW_MINIMUM_REDEMPTION` | 400 | No | Redemption amount below minimum |
| `REFERRAL_CODE_INVALID` | 400 | No | Code does not exist |
| `REFERRAL_CODE_EXPIRED` | 400 | No | Code has passed its expiry date |
| `REFERRAL_CODE_EXHAUSTED` | 400 | No | Code has reached its usage limit |
| `REFERRAL_ALREADY_USED` | 400 | No | This user already used the code |
| `SELF_REFERRAL` | 400 | No | Cannot use your own referral code |
| `UNAUTHORIZED` | 401 | No | Authentication required |
| `FORBIDDEN` | 403 | No | Access denied |
| `RATE_LIMITED` | 429 | Yes | Too many requests |
| `INTERNAL_ERROR` | 500 | Yes | Server error |
| `SERVICE_UNAVAILABLE` | 503 | Yes | Service temporarily unavailable |

### Response Headers

All responses include:
- `X-Correlation-ID` - Request trace identifier
- `X-Response-Time` - Request duration in milliseconds

---

## Authentication

### GET /auth/google/redirect

Redirect user to Google OAuth consent screen.

**Response:** 302 redirect to Google

---

### GET /auth/google/callback

Handle Google OAuth callback. Creates user account if first-time login, or links Google account to existing user.

**Response:** Redirect to dashboard with authenticated session

---

### POST /api/v1/login

Authenticate user and receive API token.

**Request:**

```json
{
  "email": "user@example.com",
  "password": "string"
}
```

**Response 200:**

```json
{
  "token": "sanctum_token_here",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "roles": ["customer"]
  }
}
```

---

### POST /api/v1/logout

Revoke current authentication token.

- **Headers:** `Authorization: Bearer {token}`
- **Response:** 204 No Content

---

## Cart

Cart endpoints support both authenticated users and anonymous sessions.

### GET /api/v1/cart

Retrieve current cart with items and totals.

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "items": [
      {
        "id": 1,
        "product_id": 42,
        "product_name": "Organic Coffee",
        "product_sku": "COF-001",
        "quantity": 2,
        "price_cents": 1500,
        "tax_cents": 150,
        "line_total_cents": 3300
      }
    ],
    "subtotal_cents": 3000,
    "tax_cents": 300,
    "total_cents": 3300,
    "item_count": 2
  }
}
```

---

### POST /api/v1/cart/items

Add item to cart.

**Request:**

```json
{
  "product_id": 42,
  "quantity": 2
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "product_id": 42,
    "quantity": 2,
    "price_cents": 1500,
    "tax_cents": 150
  }
}
```

**Errors:**
- 422 `INSUFFICIENT_STOCK` - Not enough stock
- 422 `CART_ALREADY_COMPLETED` - Cart already checked out

---

### PUT /api/v1/cart/items/{item}

Update cart item quantity.

**Request:**

```json
{
  "quantity": 3
}
```

**Response 200:** Updated cart item

**Note:** Setting quantity to 0 removes the item.

---

### DELETE /api/v1/cart/items/{item}

Remove item from cart.

**Response:** 204 No Content

---

### DELETE /api/v1/cart

Clear entire cart.

**Response:** 204 No Content

---

## Promotions

### POST /api/v1/cart/apply-promotion

Apply a promotion code to the current cart.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "code": "SUMMER20"
}
```

**Response 200:**

```json
{
  "data": {
    "promotion_id": 3,
    "code": "SUMMER20",
    "discount_cents": 600,
    "cart_total_cents": 2700
  }
}
```

**Errors:**
- 422 - Promotion invalid, expired, or not applicable to cart

---

### POST /api/v1/cart/validate-promotion

Validate a promotion code without applying it.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "code": "SUMMER20"
}
```

**Response 200:** Promotion details and discount preview

---

### GET /api/v1/promotions/active

List currently active promotions for the tenant.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:**

```json
{
  "data": [
    {
      "id": 3,
      "code": "SUMMER20",
      "description": "20% off summer items",
      "discount_type": "percentage",
      "discount_value": 20,
      "expires_at": "2026-08-31T23:59:59Z"
    }
  ]
}
```

---

## Checkout

Guest checkout is supported — authentication is optional. Unauthenticated users may pass `guest_email` and `guest_name` in the request body. Shipping method selection is required.

### POST /api/v1/checkout

Initiate checkout process. Creates order from cart with stock reservation. Works for both authenticated users and guests.

- **Headers:** `Authorization: Bearer {token}` (optional — omit for guest checkout)
- **Headers:** `Idempotency-Key: {uuid}` (required for duplicate detection)

**Request:**

```json
{
  "cart_id": 1,
  "currency": "USD",
  "promotion_code": "SAVE10",
  "redeem_points": 500
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `cart_id` | integer | Yes | Cart to check out |
| `currency` | string (3) | Yes | Checkout currency — must be one of `USD`, `MYR`, `EUR`, `GBP`, `SGD`, `AUD`, `JPY`, `CAD` |
| `promotion_code` | string | No | Promotion code to apply |
| `redeem_points` | integer | No | Loyalty points to redeem for a discount |

**Response 201:**

```json
{
  "data": {
    "order": {
      "id": 1,
      "status": "pending",
      "currency": "USD",
      "base_currency": "MYR",
      "exchange_rate": 0.22,
      "subtotal_cents": 660,
      "tax_cents": 66,
      "discount_cents": 0,
      "total_cents": 726,
      "base_total_cents": 3300,
      "items": [...]
    },
    "payment_intent": {
      "id": 1,
      "client_secret": "pi_xxx_secret_xxx",
      "status": "requires_payment_method",
      "amount_cents": 726
    }
  }
}
```

**Multi-currency notes:**
- `total_cents` and all amount fields are in the requested `currency`
- `base_total_cents` is the original amount in the tenant's base currency (useful for reporting)
- `exchange_rate` is the rate used at the time of checkout (locked in — never changes)
- When `currency` equals the tenant's base currency, `exchange_rate` is always `1.0`

**Errors:**
- 422 `CART_EMPTY` - Cart has no items
- 422 `CART_ALREADY_COMPLETED` - Cart already checked out
- 422 `INSUFFICIENT_STOCK` - Stock no longer available
- 422 validation - `currency` not in supported list
- 409 `INVALID_IDEMPOTENCY_KEY` - Idempotency key reused with different payload

---

### POST /api/v1/checkout/confirm-payment

Confirm payment intent after client-side payment completion.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "payment_intent_id": 1
}
```

**Response 200:**

```json
{
  "data": {
    "order": {
      "id": 1,
      "status": "paid",
      "total_cents": 2700
    },
    "payment_intent": {
      "id": 1,
      "status": "succeeded"
    }
  }
}
```

**Errors:**
- 422 `PAYMENT_ALREADY_CONFIRMED` - Payment already processed
- 422 `PAYMENT_FAILED` - Payment was declined

---

## Orders

### GET /api/v1/orders

List authenticated user's orders.

- **Headers:** `Authorization: Bearer {token}`
- **Query:** `page` (optional, default: 1)

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "status": "paid",
      "subtotal_cents": 3000,
      "tax_cents": 300,
      "discount_cents": 0,
      "total_cents": 3300,
      "refunded_amount_cents": 0,
      "created_at": "2026-02-03T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 42
  }
}
```

---

### GET /api/v1/orders/{order}

Get order details with items, payment, and refunds.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "status": "paid",
    "subtotal_cents": 3000,
    "tax_cents": 300,
    "discount_cents": 0,
    "total_cents": 3300,
    "refunded_amount_cents": 0,
    "items": [
      {
        "id": 1,
        "product_id": 42,
        "product_name": "Organic Coffee",
        "quantity": 2,
        "price_cents": 1500,
        "tax_cents": 150,
        "line_total_cents": 3300
      }
    ],
    "payment_intent": {
      "id": 1,
      "provider": "stripe",
      "provider_reference": "pi_xxx",
      "status": "succeeded",
      "amount_cents": 3300
    },
    "refunds": [],
    "created_at": "2026-02-03T10:00:00Z"
  }
}
```

**Errors:**
- 403 `FORBIDDEN` - Order belongs to another user
- 404 `ORDER_NOT_FOUND` - Order does not exist

---

## Refunds

### POST /api/v1/orders/{order}/refunds

Request a refund for an order.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "amount_cents": 1500,
  "reason": "Product damaged on arrival"
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "amount_cents": 1500,
    "reason": "Product damaged on arrival",
    "status": "requested",
    "created_at": "2026-02-03T12:00:00Z"
  }
}
```

**Errors:**
- 422 `ORDER_NOT_PAID` - Order has not been paid
- 422 `ORDER_NOT_REFUNDABLE` - Order already fully refunded
- 422 `REFUND_EXCEEDS_TOTAL` - Refund amount exceeds remaining refundable amount
- 403 `FORBIDDEN` - Order belongs to another user

---

## Loyalty Points

All loyalty endpoints require authentication.

### GET /api/v1/loyalty

Get the authenticated user's loyalty account — balance and lifetime totals. Creates the account automatically on first access.

**Response 200:**

```json
{
  "data": {
    "points_balance": 1250,
    "total_points_earned": 1750,
    "total_points_redeemed": 500
  }
}
```

---

### GET /api/v1/loyalty/transactions

Paginated history of point transactions.

**Query:** `page` (optional)

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "type": "earned",
      "points": 500,
      "balance_after": 500,
      "description": "Order #ORD-001 reward",
      "created_at": "2026-03-20T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 4 }
}
```

---

### POST /api/v1/loyalty/redeem

Redeem points (minimum 100 points).

**Request:**

```json
{ "points": 200 }
```

**Response 201:**

```json
{
  "data": {
    "points_redeemed": 200,
    "new_balance": 1050
  }
}
```

**Errors:**
- 400 `INSUFFICIENT_POINTS` — Not enough points
- 400 `BELOW_MINIMUM_REDEMPTION` — Below the 100-point minimum

---

## Referral Links

All referral endpoints require authentication.

### GET /api/v1/referral

Get current user's referral code. Auto-generates one if none exists.

**Response 200:**

```json
{
  "data": {
    "code": "ABCDEF123456",
    "shareable_url": "https://yourdomain.com/r/ABCDEF123456",
    "status": "active",
    "referrer_reward_points": 500,
    "referee_discount_percent": 10,
    "max_uses": 10,
    "used_count": 3,
    "expires_at": "2026-04-19T00:00:00Z",
    "is_active": true
  }
}
```

---

### GET /api/v1/referral/stats

Referral usage statistics with masked referee details.

**Response 200:**

```json
{
  "data": {
    "total_usages": 3,
    "total_points_earned": 1500,
    "usages": [
      { "referee_email": "j***@example.com", "used_at": "2026-03-21T08:00:00Z" }
    ]
  }
}
```

---

### POST /api/v1/referral/apply

Apply a referral code. Referrer earns loyalty points; referee receives a discount coupon code.

**Request:**

```json
{ "code": "ABCDEF123456" }
```

**Response 201:**

```json
{
  "data": {
    "referee_coupon_code": "REF-XXXXXXXX",
    "referee_discount_percent": 10,
    "message": "Code applied! You've received a 10% discount coupon."
  }
}
```

**Errors:**
- 400 `REFERRAL_CODE_EXPIRED` — Code has passed expiry
- 400 `REFERRAL_CODE_EXHAUSTED` — Code reached max uses
- 400 `REFERRAL_CODE_INVALID` — Code not found
- 400 `SELF_REFERRAL` — Cannot use your own code
- 400 `REFERRAL_ALREADY_USED` — Already used this code

---

### POST /api/v1/referral/regenerate

Deactivate the current code and issue a fresh one with the same reward settings.

**Response 201:** New referral code object (same shape as `GET /api/v1/referral`)

---

## Search

Search endpoints use Typesense for full-text, typo-tolerant search. Fall back to database query when no `q` parameter is supplied.

### GET /api/v1/search/products

Search products. Public — no authentication required.

**Query Parameters:**
- `q` — search term (triggers Typesense; omit for full listing)
- `category` — filter by category slug
- `min_price` — minimum price in cents
- `max_price` — maximum price in cents
- `per_page` (default: 15)
- `page`

**Response 200:**

```json
{
  "data": [
    {
      "id": 42,
      "name": "Organic Coffee",
      "slug": "organic-coffee",
      "sku": "COF-001",
      "price_cents": 1500,
      "sale_price": null,
      "currency": "USD",
      "is_featured": false,
      "category_name": "Beverages",
      "image": "https://..."
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42 }
}
```

---

### GET /api/v1/search/categories

Search categories. Public — no authentication required.

**Query:** `q`, `per_page`

**Response 200:**

```json
{
  "data": [
    { "id": 1, "name": "Beverages", "slug": "beverages", "description": "Hot and cold drinks" }
  ]
}
```

---

### GET /api/v1/search/orders

Search the authenticated user's orders.

- **Headers:** `Authorization: Bearer {token}`

**Query:** `q` (searches order number), `status`, `per_page`

**Response 200:** Paginated order summaries (same shape as `GET /api/v1/orders`)

---

## Products & Categories

### GET /api/v1/products

List products with optional filtering.

**Query Parameters:**
- `category_id` (optional) - Filter by category
- `search` (optional) - Search by name/SKU
- `page` (optional) - Pagination

**Response 200:**

```json
{
  "data": [
    {
      "id": 42,
      "sku": "COF-001",
      "name": "Organic Coffee",
      "slug": "organic-coffee",
      "price_cents": 1500,
      "sale_price_cents": null,
      "currency": "USD",
      "is_active": true,
      "stock_available": 100,
      "average_rating": 4.5,
      "review_count": 12
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150
  }
}
```

---

### GET /api/v1/products/{slug}

Get product details.

**Response 200:** Full product object including price history and review summary.

---

### GET /api/v1/categories

List all categories.

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Beverages",
      "slug": "beverages",
      "description": "Hot and cold drinks",
      "product_count": 25
    }
  ]
}
```

---

## Reviews

### GET /api/v1/products/{slug}/reviews

List reviews for a product.

**Query Parameters:**
- `page` (optional)

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "rating": 5,
      "body": "Excellent product, very fresh.",
      "user": {
        "name": "Jane D."
      },
      "created_at": "2026-02-10T08:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 12,
    "average_rating": 4.5
  }
}
```

---

### POST /api/v1/products/{slug}/reviews

Submit a review for a product.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "rating": 5,
  "body": "Excellent product, very fresh."
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "rating": 5,
    "body": "Excellent product, very fresh.",
    "created_at": "2026-02-10T08:30:00Z"
  }
}
```

**Errors:**
- 422 - Rating required (1–5), body required
- 409 - User has already reviewed this product

---

## Chat / Conversations

All chat endpoints require authentication.

### GET /api/v1/conversations

List the authenticated user's conversations.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "subject": "Order #123 issue",
      "status": "open",
      "unread_count": 2,
      "last_message_at": "2026-02-20T14:00:00Z"
    }
  ]
}
```

---

### POST /api/v1/conversations

Start a new conversation.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "subject": "Order #123 issue",
  "message": "My package arrived damaged."
}
```

**Response 201:**

```json
{
  "data": {
    "id": 1,
    "subject": "Order #123 issue",
    "status": "open",
    "created_at": "2026-02-20T13:00:00Z"
  }
}
```

---

### GET /api/v1/conversations/{id}

Get a conversation with its messages.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "subject": "Order #123 issue",
    "status": "open",
    "messages": [
      {
        "id": 1,
        "body": "My package arrived damaged.",
        "sender": { "id": 5, "name": "Jane Doe" },
        "created_at": "2026-02-20T13:00:00Z"
      }
    ]
  }
}
```

**Errors:**
- 403 `FORBIDDEN` - Conversation belongs to another user

---

### POST /api/v1/conversations/{id}/messages

Send a message in a conversation.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "body": "Can you please provide photos?"
}
```

**Response 201:**

```json
{
  "data": {
    "id": 2,
    "body": "Can you please provide photos?",
    "sender": { "id": 5, "name": "Jane Doe" },
    "created_at": "2026-02-20T13:05:00Z"
  }
}
```

New messages are broadcast in real-time via Laravel Reverb to the `conversation.{id}` channel.

---

### PATCH /api/v1/conversations/{id}/close

Close a conversation.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:** Updated conversation with `status: "closed"`

---

### POST /api/v1/conversations/{id}/read

Mark all messages in a conversation as read.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

## Notifications

All endpoints require authentication. Notification objects include:

```json
{
  "id": "uuid",
  "type": "App\\Notifications\\OrderStatusChanged",
  "data": { "order_id": 42, "status": "paid", "message": "Your order has been paid." },
  "read_at": null,
  "created_at": "2026-03-15T10:00:00Z"
}
```

### GET /api/v1/notifications

List recent notifications (up to 20, sorted newest first).

- **Headers:** `Authorization: Bearer {token}`

**Response 200:** `{ "data": [ ...notifications ] }`

---

### POST /api/v1/notifications/{id}/read

Mark a single notification as read.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

### DELETE /api/v1/notifications/{id}

Delete a notification.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

### POST /api/v1/notifications/read-all

Mark all unread notifications as read.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

### DELETE /api/v1/notifications

Clear all notifications for the authenticated user.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

## Addresses

Manage the user's saved shipping address book.

### GET /api/v1/addresses

List all saved addresses.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "label": "Home",
      "line1": "123 Main St",
      "line2": null,
      "city": "Kuala Lumpur",
      "state": "Selangor",
      "postcode": "50000",
      "country": "MY",
      "is_default": true
    }
  ]
}
```

---

### POST /api/v1/addresses

Create a new address.

- **Headers:** `Authorization: Bearer {token}`

**Request:**

```json
{
  "label": "Office",
  "line1": "456 Business Park",
  "city": "Petaling Jaya",
  "state": "Selangor",
  "postcode": "47810",
  "country": "MY"
}
```

**Response 201:** Created address object.

---

### PUT /api/v1/addresses/{id}

Update an existing address.

- **Headers:** `Authorization: Bearer {token}`

**Request:** Same fields as POST (all optional).

**Response 200:** Updated address object.

---

### DELETE /api/v1/addresses/{id}

Delete an address. Cannot delete the default address while others exist.

- **Headers:** `Authorization: Bearer {token}`

**Response:** 204 No Content

---

### PATCH /api/v1/addresses/{id}/default

Set an address as the user's default shipping address.

- **Headers:** `Authorization: Bearer {token}`

**Response 200:** Updated address object with `is_default: true`.

---

## Shipping Methods

### GET /api/v1/shipping-methods

List available shipping methods for the current tenant. Public — no authentication required.

**Response 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Standard Shipping",
      "description": "3–5 business days",
      "price_cents": 500,
      "is_free": false,
      "min_order_cents": null
    },
    {
      "id": 2,
      "name": "Free Shipping",
      "description": "On orders over RM 150",
      "price_cents": 0,
      "is_free": true,
      "min_order_cents": 15000
    }
  ]
}
```

---

## Waitlist

### POST /api/v1/products/{slug}/waitlist

Subscribe to a back-in-stock email alert for an out-of-stock product. Public — no authentication required.

**Request:**

```json
{
  "email": "customer@example.com"
}
```

**Response 201:** Empty body (idempotent — re-subscribing with the same email is a no-op).

**Response 404:** Product not found or inactive.

---

## Webhooks

### POST /api/webhooks/stripe

Handle Stripe webhook events. Verifies signature and processes:
- `payment_intent.succeeded` - Marks order as paid
- `payment_intent.payment_failed` - Marks payment as failed
- `charge.refunded` - Marks refund as succeeded

**Headers:** `Stripe-Signature: {signature}`

**Response:** 200 OK

---

## Controller Design Principles

- Controllers are **thin** (5–7 lines max)
- All logic lives in **Use Cases / Domain Actions**
- Transactions handled at **service layer**
- JSON responses use **API Resources** for consistency
- Domain exceptions transformed via **middleware**
