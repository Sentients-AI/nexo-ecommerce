/**
 * Error codes mirroring App\Domain\Shared\Enums\ErrorCode
 */

export enum ErrorCode {
    // Cart errors
    CartEmpty = 'CART_EMPTY',
    CartNotFound = 'CART_NOT_FOUND',
    CartAlreadyCompleted = 'CART_ALREADY_COMPLETED',

    // Order errors
    OrderNotFound = 'ORDER_NOT_FOUND',
    OrderNotRefundable = 'ORDER_NOT_REFUNDABLE',
    OrderAlreadyPaid = 'ORDER_ALREADY_PAID',

    // Payment errors
    PaymentFailed = 'PAYMENT_FAILED',
    PaymentIntentNotFound = 'PAYMENT_INTENT_NOT_FOUND',
    PaymentAlreadyProcessed = 'PAYMENT_ALREADY_PROCESSED',
    PaymentGatewayError = 'PAYMENT_GATEWAY_ERROR',

    // Inventory errors
    InsufficientStock = 'INSUFFICIENT_STOCK',
    ProductNotFound = 'PRODUCT_NOT_FOUND',

    // Refund errors
    RefundNotFound = 'REFUND_NOT_FOUND',
    RefundAmountExceedsLimit = 'REFUND_AMOUNT_EXCEEDS_LIMIT',
    RefundAlreadyProcessed = 'REFUND_ALREADY_PROCESSED',

    // Review errors
    ReviewAlreadySubmitted = 'REVIEW_ALREADY_SUBMITTED',

    // Idempotency errors
    IdempotencyConflict = 'IDEMPOTENCY_CONFLICT',

    // Authorization errors
    Unauthorized = 'UNAUTHORIZED',
    Forbidden = 'FORBIDDEN',

    // Validation errors
    ValidationFailed = 'VALIDATION_FAILED',

    // Generic errors
    InternalError = 'INTERNAL_ERROR',
    ServiceUnavailable = 'SERVICE_UNAVAILABLE',
    RateLimitExceeded = 'RATE_LIMIT_EXCEEDED',
}

/**
 * Determines if an error code indicates a retryable error.
 * Mirrors ErrorCode::isRetryable() from backend.
 */
export function isRetryable(code: ErrorCode): boolean {
    return [
        ErrorCode.PaymentGatewayError,
        ErrorCode.InternalError,
        ErrorCode.ServiceUnavailable,
        ErrorCode.RateLimitExceeded,
    ].includes(code);
}

/**
 * Returns the HTTP status code for an error code.
 * Mirrors ErrorCode::httpStatus() from backend.
 */
export function httpStatusForCode(code: ErrorCode): number {
    const statusMap: Record<ErrorCode, number> = {
        [ErrorCode.CartEmpty]: 422,
        [ErrorCode.CartAlreadyCompleted]: 422,
        [ErrorCode.OrderNotRefundable]: 422,
        [ErrorCode.OrderAlreadyPaid]: 422,
        [ErrorCode.PaymentAlreadyProcessed]: 422,
        [ErrorCode.InsufficientStock]: 422,
        [ErrorCode.RefundAmountExceedsLimit]: 422,
        [ErrorCode.RefundAlreadyProcessed]: 422,
        [ErrorCode.ReviewAlreadySubmitted]: 422,
        [ErrorCode.ValidationFailed]: 422,

        [ErrorCode.CartNotFound]: 404,
        [ErrorCode.OrderNotFound]: 404,
        [ErrorCode.PaymentIntentNotFound]: 404,
        [ErrorCode.ProductNotFound]: 404,
        [ErrorCode.RefundNotFound]: 404,

        [ErrorCode.IdempotencyConflict]: 409,

        [ErrorCode.Unauthorized]: 401,
        [ErrorCode.Forbidden]: 403,

        [ErrorCode.RateLimitExceeded]: 429,

        [ErrorCode.PaymentFailed]: 500,
        [ErrorCode.PaymentGatewayError]: 500,
        [ErrorCode.InternalError]: 500,

        [ErrorCode.ServiceUnavailable]: 503,
    };

    return statusMap[code] ?? 500;
}

/**
 * User-friendly messages for error codes
 */
export function messageForCode(code: ErrorCode): string {
    const messages: Record<ErrorCode, string> = {
        [ErrorCode.CartEmpty]: 'Your cart is empty.',
        [ErrorCode.CartNotFound]: 'Cart not found.',
        [ErrorCode.CartAlreadyCompleted]: 'This cart has already been checked out.',

        [ErrorCode.OrderNotFound]: 'Order not found.',
        [ErrorCode.OrderNotRefundable]: 'This order cannot be refunded.',
        [ErrorCode.OrderAlreadyPaid]: 'This order has already been paid.',

        [ErrorCode.PaymentFailed]: 'Payment failed. Please try again.',
        [ErrorCode.PaymentIntentNotFound]: 'Payment information not found.',
        [ErrorCode.PaymentAlreadyProcessed]: 'This payment has already been processed.',
        [ErrorCode.PaymentGatewayError]: 'Payment service is temporarily unavailable. Please try again.',

        [ErrorCode.InsufficientStock]: 'Some items are no longer available in the requested quantity.',
        [ErrorCode.ProductNotFound]: 'Product not found.',

        [ErrorCode.RefundNotFound]: 'Refund request not found.',
        [ErrorCode.RefundAmountExceedsLimit]: 'Refund amount exceeds the refundable limit.',
        [ErrorCode.RefundAlreadyProcessed]: 'This refund has already been processed.',
        [ErrorCode.ReviewAlreadySubmitted]: 'You have already reviewed this product.',

        [ErrorCode.IdempotencyConflict]: 'A request with this ID is already being processed.',

        [ErrorCode.Unauthorized]: 'Please sign in to continue.',
        [ErrorCode.Forbidden]: 'You do not have permission to perform this action.',

        [ErrorCode.ValidationFailed]: 'Please check your input and try again.',

        [ErrorCode.InternalError]: 'An unexpected error occurred. Please try again.',
        [ErrorCode.ServiceUnavailable]: 'Service temporarily unavailable. Please try again later.',
        [ErrorCode.RateLimitExceeded]: 'Too many requests. Please wait a moment and try again.',
    };

    return messages[code] ?? 'An unexpected error occurred.';
}
