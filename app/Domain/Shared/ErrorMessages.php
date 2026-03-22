<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Enums\ErrorCode;

final class ErrorMessages
{
    /**
     * Get a user-safe message for an error code.
     * These messages are safe to display to end users.
     */
    public static function getUserMessage(ErrorCode $code): string
    {
        return match ($code) {
            ErrorCode::CartEmpty => 'Your cart is empty. Please add items before checkout.',
            ErrorCode::CartNotFound => 'We could not find your cart. Please try again.',
            ErrorCode::CartAlreadyCompleted => 'This cart has already been checked out.',
            ErrorCode::InsufficientStock => 'Some items in your cart are no longer available in the requested quantity.',
            ErrorCode::ProductNotFound => 'The requested product could not be found.',
            ErrorCode::PaymentFailed => 'We were unable to process your payment. Please try again or use a different payment method.',
            ErrorCode::PaymentGatewayError => 'There was an issue with the payment system. Please try again in a few moments.',
            ErrorCode::PaymentAlreadyProcessed => 'This payment has already been processed.',
            ErrorCode::PaymentIntentNotFound => 'Payment information not found.',
            ErrorCode::OrderNotFound => 'We could not find the order you are looking for.',
            ErrorCode::OrderNotRefundable => 'This order is not eligible for a refund at this time.',
            ErrorCode::OrderAlreadyPaid => 'This order has already been paid.',
            ErrorCode::RefundAmountExceedsLimit => 'The refund amount requested exceeds the refundable amount.',
            ErrorCode::RefundAlreadyProcessed => 'This refund has already been processed.',
            ErrorCode::RefundNotFound => 'We could not find the refund you are looking for.',
            ErrorCode::Forbidden => 'You do not have permission to perform this action.',
            ErrorCode::Unauthorized => 'Please log in to continue.',
            ErrorCode::ValidationFailed => 'Please check your input and try again.',
            ErrorCode::InternalError => 'Something went wrong. Please try again later.',
            ErrorCode::ServiceUnavailable => 'The service is temporarily unavailable. Please try again later.',
            ErrorCode::RateLimitExceeded => 'Too many requests. Please wait a moment before trying again.',
            ErrorCode::ReviewAlreadySubmitted => 'You have already reviewed this product.',
            ErrorCode::IdempotencyConflict => 'This request is already being processed.',
            ErrorCode::ConversationNotFound => 'We could not find this conversation.',
            ErrorCode::ConversationClosed => 'This conversation has been closed.',
            ErrorCode::LoyaltyAccountNotFound => 'We could not find a loyalty account for this user.',
            ErrorCode::InsufficientPoints => 'You do not have enough loyalty points for this redemption.',
            ErrorCode::BelowMinimumRedemption => 'The minimum number of points required for redemption has not been met.',
            ErrorCode::ReferralCodeInvalid => 'The referral code you entered is invalid.',
            ErrorCode::ReferralCodeExpired => 'This referral code has expired.',
            ErrorCode::ReferralCodeExhausted => 'This referral code has reached its usage limit.',
            ErrorCode::ReferralAlreadyUsed => 'You have already used a referral code.',
            ErrorCode::SelfReferral => 'You cannot use your own referral code.',
        };
    }

    /**
     * Get detailed technical message (for logs, not for users).
     */
    public static function getTechnicalMessage(ErrorCode $code): string
    {
        return match ($code) {
            ErrorCode::CartEmpty => 'Checkout attempted with empty cart',
            ErrorCode::CartNotFound => 'Cart ID not found in database',
            ErrorCode::CartAlreadyCompleted => 'Cart status is completed',
            ErrorCode::InsufficientStock => 'Stock reservation failed due to insufficient quantity',
            ErrorCode::ProductNotFound => 'Product ID not found in database',
            ErrorCode::PaymentFailed => 'Payment gateway returned failure status',
            ErrorCode::PaymentGatewayError => 'Payment gateway communication error',
            ErrorCode::PaymentAlreadyProcessed => 'Payment intent status is terminal',
            ErrorCode::PaymentIntentNotFound => 'Payment intent ID not found in database',
            ErrorCode::OrderNotFound => 'Order lookup returned null',
            ErrorCode::OrderNotRefundable => 'Order status does not allow refund',
            ErrorCode::OrderAlreadyPaid => 'Order status is already paid',
            ErrorCode::RefundAmountExceedsLimit => 'Refund amount exceeds remaining refundable amount',
            ErrorCode::RefundAlreadyProcessed => 'Refund status is terminal',
            ErrorCode::RefundNotFound => 'Refund ID not found in database',
            ErrorCode::Forbidden => 'Authorization check failed',
            ErrorCode::Unauthorized => 'Authentication required',
            ErrorCode::ValidationFailed => 'Request validation failed',
            ErrorCode::InternalError => 'Unhandled exception occurred',
            ErrorCode::ServiceUnavailable => 'Service dependency unavailable',
            ErrorCode::RateLimitExceeded => 'Rate limit threshold exceeded',
            ErrorCode::ReviewAlreadySubmitted => 'Duplicate review submission attempted',
            ErrorCode::IdempotencyConflict => 'Idempotency key already in use with different payload',
            ErrorCode::ConversationNotFound => 'Conversation ID not found in database',
            ErrorCode::ConversationClosed => 'Conversation status is closed',
            ErrorCode::LoyaltyAccountNotFound => 'Loyalty account not found for user ID',
            ErrorCode::InsufficientPoints => 'Points balance below requested redemption amount',
            ErrorCode::BelowMinimumRedemption => 'Redemption amount below configured minimum threshold',
            ErrorCode::ReferralCodeInvalid => 'Referral code not found or inactive',
            ErrorCode::ReferralCodeExpired => 'Referral code validity window has elapsed',
            ErrorCode::ReferralCodeExhausted => 'Referral code usage_limit reached',
            ErrorCode::ReferralAlreadyUsed => 'User has already applied a referral code',
            ErrorCode::SelfReferral => 'Referee user ID matches referrer user ID',
        };
    }
}
