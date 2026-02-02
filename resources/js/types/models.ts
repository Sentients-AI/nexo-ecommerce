/**
 * TypeScript models mirroring backend Eloquent models
 */

// ========================================
// Enums
// ========================================

export enum OrderStatus {
    Pending = 'pending',
    Paid = 'paid',
    Fulfilled = 'fulfilled',
    Cancelled = 'cancelled',
    AwaitingPayment = 'awaiting_payment',
    Failed = 'failed',
    Packed = 'packed',
    Shipped = 'shipped',
    Delivered = 'delivered',
    Refunded = 'refunded',
    PartiallyRefunded = 'partially_refunded',
}

export enum RefundStatus {
    Requested = 'requested',
    Approved = 'approved',
    Processing = 'processing',
    Succeeded = 'succeeded',
    Failed = 'failed',
    Rejected = 'rejected',
    Cancelled = 'cancelled',
    PendingApproval = 'pending_approval',
}

export enum PaymentStatus {
    Pending = 'pending',
    RequiresAction = 'requires_action',
    Processing = 'processing',
    Succeeded = 'succeeded',
    Failed = 'failed',
    Cancelled = 'cancelled',
}

// ========================================
// Base Types
// ========================================

export interface TimestampedModel {
    created_at: string;
    updated_at: string;
}

// ========================================
// Product Domain
// ========================================

export interface Category extends TimestampedModel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    is_active: boolean;
    sort_order: number;
}

export interface Stock {
    id: number;
    product_id: number;
    quantity: number;
    reserved_quantity: number;
    low_stock_threshold: number;
}

export interface Product extends TimestampedModel {
    id: number;
    sku: string;
    name: string;
    slug: string;
    description: string | null;
    short_description: string | null;
    price_cents: number;
    sale_price: number | null;
    category_id: number | null;
    is_active: boolean;
    is_featured: boolean;
    images: string[];
    meta_title: string | null;
    meta_description: string | null;
    currency: string;
    // Computed/eager loaded
    category?: Category;
    stock?: Stock;
    effective_price?: number;
    discount_percentage?: number | null;
}

// ========================================
// Cart Domain
// ========================================

export interface CartItem {
    id: number;
    cart_id: number;
    product_id: number;
    quantity: number;
    price: number;
    product?: Product;
}

export interface Cart extends TimestampedModel {
    id: number;
    user_id: number | null;
    session_id: string;
    completed_at: string | null;
    items?: CartItem[];
    // Computed
    total_items?: number;
    subtotal?: number;
}

// ========================================
// Order Domain
// ========================================

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
    product?: Product;
}

export interface PaymentIntent extends TimestampedModel {
    id: number;
    order_id: number;
    stripe_payment_intent_id: string;
    client_secret: string | null;
    status: PaymentStatus;
    amount_cents: number;
    currency: string;
    error_message: string | null;
}

export interface Order extends TimestampedModel {
    id: number;
    user_id: number;
    order_number: string;
    status: OrderStatus;
    subtotal_cents: number;
    tax_cents: number;
    shipping_cost_cents: number;
    total_cents: number;
    refunded_amount_cents: number;
    currency: string;
    items?: OrderItem[];
    payment_intent?: PaymentIntent;
    refunds?: Refund[];
}

// ========================================
// Refund Domain
// ========================================

export interface Refund extends TimestampedModel {
    id: number;
    order_id: number;
    payment_intent_id: number;
    amount_cents: number;
    currency: string;
    status: RefundStatus;
    reason: string | null;
    approved_by: number | null;
    approved_at: string | null;
    external_refund_id: string | null;
    order?: Order;
}

// ========================================
// User Domain
// ========================================

export interface User extends TimestampedModel {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

// ========================================
// Utility Types
// ========================================

export function isRefundableStatus(status: OrderStatus): boolean {
    return [
        OrderStatus.Paid,
        OrderStatus.Packed,
        OrderStatus.Shipped,
        OrderStatus.Delivered,
        OrderStatus.Fulfilled,
        OrderStatus.PartiallyRefunded,
    ].includes(status);
}

export function isTerminalOrderStatus(status: OrderStatus): boolean {
    return [
        OrderStatus.Cancelled,
        OrderStatus.Failed,
        OrderStatus.Refunded,
    ].includes(status);
}

export function isTerminalRefundStatus(status: RefundStatus): boolean {
    return [
        RefundStatus.Succeeded,
        RefundStatus.Failed,
        RefundStatus.Rejected,
        RefundStatus.Cancelled,
    ].includes(status);
}
