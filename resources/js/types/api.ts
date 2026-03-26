/**
 * API response types matching backend Resources
 */

import type { Order, OrderItem, PaymentIntent, Refund, Product, Cart, CartItem, Category } from './models';
import type { ErrorCode } from './errors';

// ========================================
// Generic API Response Types
// ========================================

export interface ApiSuccessResponse<T> {
    success: true;
    data: T;
}

export interface ApiErrorResponse {
    success: false;
    error: {
        code: ErrorCode;
        message: string;
        retryable: boolean;
    };
}

export type ApiResponse<T> = ApiSuccessResponse<T> | ApiErrorResponse;

// ========================================
// Checkout API
// ========================================

export interface CheckoutRequest {
    idempotency_key: string;
}

export interface CheckoutSuccessData {
    order: OrderApiResource;
    client_secret: string;
    status: 'pending' | 'requires_payment';
}

export type CheckoutResponse = ApiResponse<CheckoutSuccessData>;

export interface ConfirmPaymentRequest {
    order_id: number;
    payment_intent_id: string;
}

export interface ConfirmPaymentSuccessData {
    order: OrderApiResource;
    status: 'succeeded' | 'requires_action' | 'pending';
}

export type ConfirmPaymentResponse = ApiResponse<ConfirmPaymentSuccessData>;

// ========================================
// Order API Resources
// ========================================

export interface OrderItemApiResource {
    id: number;
    order_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
}

export interface PaymentIntentApiResource {
    id: number;
    order_id: number;
    stripe_payment_intent_id: string;
    client_secret: string | null;
    status: string;
    amount_cents: number;
    currency: string;
    error_message: string | null;
}

export interface OrderApiResource {
    id: number;
    order_number: string;
    status: string;
    subtotal_cents: number;
    tax_cents: number;
    shipping_cost_cents: number;
    total_cents: number;
    refunded_amount_cents: number;
    currency: string;
    items?: OrderItemApiResource[];
    payment_intent?: PaymentIntentApiResource;
    created_at: string;
    updated_at: string;
}

// ========================================
// Refund API
// ========================================

export interface RequestRefundRequest {
    order_id: number;
    amount_cents?: number;
    reason: string;
}

export interface RefundApiResource {
    id: number;
    order_id: number;
    amount_cents: number;
    currency: string;
    status: string;
    reason: string | null;
    created_at: string;
    approved_at: string | null;
}

export type RequestRefundResponse = ApiResponse<{ refund: RefundApiResource }>;
export type RefundStatusResponse = ApiResponse<{ refund: RefundApiResource }>;

// ========================================
// Product API
// ========================================

export interface ProductApiResource {
    id: number;
    sku: string;
    name: string;
    slug: string;
    description: string | null;
    short_description: string | null;
    price_cents: number;
    sale_price: number | null;
    currency: string;
    is_active: boolean;
    is_featured: boolean;
    view_count?: number;
    images: string[];
    category?: CategoryApiResource;
    stock?: {
        quantity: number;
        available: number;
    };
    reviews_count?: number;
    reviews_avg_rating?: number | null;
    average_rating?: number | null;
    review_count?: number;
    tenant?: {
        id: number;
        name: string;
        slug: string;
    };
}

export interface CategoryApiResource {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

// ========================================
// Review API
// ========================================

export interface ReviewPhotoApiResource {
    id: number;
    url: string;
    order: number;
}

export interface ReviewReplyApiResource {
    id: number;
    review_id: number;
    user_id: number;
    user_name?: string;
    body: string;
    is_merchant_reply: boolean;
    created_at: string;
}

export interface ReviewApiResource {
    id: number;
    product_id: number;
    user_id: number;
    user_name?: string;
    rating: number;
    title: string;
    body: string;
    created_at: string;
    photos?: ReviewPhotoApiResource[];
    replies?: ReviewReplyApiResource[];
    helpful_count?: number;
    not_helpful_count?: number;
    user_vote?: boolean | null;
}

// ========================================
// Cart API
// ========================================

export interface CartItemApiResource {
    id: number;
    product_id: number;
    quantity: number;
    price: number;
    product?: ProductApiResource;
}

export interface CartApiResource {
    id: number;
    items: CartItemApiResource[];
    total_items: number;
    subtotal: number;
}

export interface AddToCartRequest {
    product_id: number;
    quantity: number;
}

export interface UpdateCartItemRequest {
    quantity: number;
}

export type CartResponse = ApiResponse<{ cart: CartApiResource }>;

// ========================================
// Chat API
// ========================================

export type ConversationStatus = 'open' | 'closed';
export type ConversationType = 'store' | 'support';

export interface ChatMessageApiResource {
    id: number;
    conversation_id: number;
    sender_id: number;
    sender_name: string;
    body: string;
    read_at: string | null;
    created_at: string;
}

export interface ConversationApiResource {
    id: number;
    type: ConversationType;
    status: ConversationStatus;
    subject: string | null;
    tenant_id: number | null;
    user: { id: number; name: string };
    latest_message?: ChatMessageApiResource;
    messages?: ChatMessageApiResource[];
    unread_count?: number;
    last_message_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface StoreConversationRequest {
    type: ConversationType;
    subject?: string;
    initial_message: string;
}

// ========================================
// Pagination
// ========================================

export interface PaginatedApiResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}
