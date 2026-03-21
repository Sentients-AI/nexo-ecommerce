<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum ErrorCode: string
{
    // Cart errors
    case CartEmpty = 'CART_EMPTY';
    case CartNotFound = 'CART_NOT_FOUND';
    case CartAlreadyCompleted = 'CART_ALREADY_COMPLETED';

    // Order errors
    case OrderNotFound = 'ORDER_NOT_FOUND';
    case OrderNotRefundable = 'ORDER_NOT_REFUNDABLE';
    case OrderAlreadyPaid = 'ORDER_ALREADY_PAID';

    // Payment errors
    case PaymentFailed = 'PAYMENT_FAILED';
    case PaymentIntentNotFound = 'PAYMENT_INTENT_NOT_FOUND';
    case PaymentAlreadyProcessed = 'PAYMENT_ALREADY_PROCESSED';
    case PaymentGatewayError = 'PAYMENT_GATEWAY_ERROR';

    // Inventory errors
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case ProductNotFound = 'PRODUCT_NOT_FOUND';

    // Refund errors
    case RefundNotFound = 'REFUND_NOT_FOUND';
    case RefundAmountExceedsLimit = 'REFUND_AMOUNT_EXCEEDS_LIMIT';
    case RefundAlreadyProcessed = 'REFUND_ALREADY_PROCESSED';

    // Loyalty errors
    case LoyaltyAccountNotFound = 'LOYALTY_ACCOUNT_NOT_FOUND';
    case InsufficientPoints = 'INSUFFICIENT_POINTS';
    case BelowMinimumRedemption = 'BELOW_MINIMUM_REDEMPTION';

    // Chat errors
    case ConversationNotFound = 'CONVERSATION_NOT_FOUND';
    case ConversationClosed = 'CONVERSATION_CLOSED';

    // Referral errors
    case ReferralCodeInvalid = 'REFERRAL_CODE_INVALID';
    case ReferralCodeExpired = 'REFERRAL_CODE_EXPIRED';
    case ReferralCodeExhausted = 'REFERRAL_CODE_EXHAUSTED';
    case ReferralAlreadyUsed = 'REFERRAL_ALREADY_USED';
    case SelfReferral = 'SELF_REFERRAL';

    // Review errors
    case ReviewAlreadySubmitted = 'REVIEW_ALREADY_SUBMITTED';

    // Idempotency errors
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';

    // Authorization errors
    case Unauthorized = 'UNAUTHORIZED';
    case Forbidden = 'FORBIDDEN';

    // Validation errors
    case ValidationFailed = 'VALIDATION_FAILED';

    // Generic errors
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case RateLimitExceeded = 'RATE_LIMIT_EXCEEDED';

    public function httpStatus(): int
    {
        return match ($this) {
            self::CartEmpty,
            self::CartAlreadyCompleted,
            self::OrderNotRefundable,
            self::OrderAlreadyPaid,
            self::PaymentAlreadyProcessed,
            self::InsufficientStock,
            self::RefundAmountExceedsLimit,
            self::RefundAlreadyProcessed,
            self::ReviewAlreadySubmitted,
            self::ConversationClosed,
            self::InsufficientPoints,
            self::BelowMinimumRedemption,
            self::ValidationFailed => 422,

            self::ReferralCodeInvalid,
            self::ReferralCodeExpired,
            self::ReferralCodeExhausted,
            self::ReferralAlreadyUsed,
            self::SelfReferral => 400,

            self::CartNotFound,
            self::OrderNotFound,
            self::PaymentIntentNotFound,
            self::ProductNotFound,
            self::RefundNotFound,
            self::ConversationNotFound,
            self::LoyaltyAccountNotFound => 404,

            self::IdempotencyConflict => 409,

            self::Unauthorized => 401,
            self::Forbidden => 403,

            self::RateLimitExceeded => 429,

            self::PaymentFailed,
            self::PaymentGatewayError,
            self::InternalError => 500,

            self::ServiceUnavailable => 503,
        };
    }

    public function isRetryable(): bool
    {
        return match ($this) {
            self::PaymentGatewayError,
            self::InternalError,
            self::ServiceUnavailable,
            self::RateLimitExceeded => true,
            default => false,
        };
    }
}
