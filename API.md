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

## Checkout

### POST /api/v1/checkout

Initiate checkout process. Creates order from cart with stock reservation.

- **Headers:** `Authorization: Bearer {token}`
- **Headers:** `Idempotency-Key: {uuid}` (required for duplicate detection)

**Response 201:**

```json
{
  "data": {
    "order": {
      "id": 1,
      "status": "pending",
      "subtotal_cents": 3000,
      "tax_cents": 300,
      "total_cents": 3300,
      "items": [...]
    },
    "payment_intent": {
      "id": 1,
      "client_secret": "pi_xxx_secret_xxx",
      "status": "requires_payment_method",
      "amount_cents": 3300
    }
  }
}
```

**Errors:**
- 422 `CART_EMPTY` - Cart has no items
- 422 `CART_ALREADY_COMPLETED` - Cart already checked out
- 422 `INSUFFICIENT_STOCK` - Stock no longer available
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
      "total_cents": 3300
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
      "price_cents": 1500,
      "sale_price_cents": null,
      "currency": "USD",
      "is_active": true,
      "stock_available": 100
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

### GET /api/v1/products/{id}

Get product details.

**Response 200:** Product object with full details

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
