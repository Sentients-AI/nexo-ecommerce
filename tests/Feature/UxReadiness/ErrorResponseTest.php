<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\ErrorCode;
use App\Domain\Shared\ErrorMessages;
use App\Http\Responses\ApiErrorResponse;
use App\Shared\Performance\PerformanceBudgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('ErrorMessages', function () {
    it('provides user-safe messages for all error codes', function () {
        foreach (ErrorCode::cases() as $code) {
            $message = ErrorMessages::getUserMessage($code);
            expect($message)->toBeString();
            expect($message)->not->toContain('exception');
            expect($message)->not->toContain('null');
            expect($message)->not->toContain('error:');
        }
    });

    it('provides technical messages for all error codes', function () {
        foreach (ErrorCode::cases() as $code) {
            $message = ErrorMessages::getTechnicalMessage($code);
            expect($message)->toBeString();
        }
    });

    it('returns friendly message for cart empty', function () {
        $message = ErrorMessages::getUserMessage(ErrorCode::CartEmpty);
        expect($message)->toContain('cart');
        expect($message)->toContain('empty');
    });

    it('returns friendly message for insufficient stock', function () {
        $message = ErrorMessages::getUserMessage(ErrorCode::InsufficientStock);
        expect($message)->toContain('available');
    });

    it('returns friendly message for internal error without exposing details', function () {
        $message = ErrorMessages::getUserMessage(ErrorCode::InternalError);
        expect($message)->not->toContain('exception');
        expect($message)->not->toContain('stack');
        expect($message)->toContain('try again');
    });
});

describe('ApiErrorResponse', function () {
    it('creates response with correct structure', function () {
        $response = ApiErrorResponse::fromCode(ErrorCode::CartEmpty, 'test-correlation-id');
        $json = $response->toResponse()->getData(true);

        expect($json)->toHaveKey('error');
        expect($json['error'])->toHaveKey('code');
        expect($json['error'])->toHaveKey('message');
        expect($json['error'])->toHaveKey('retryable');
        expect($json['error'])->toHaveKey('correlation_id');
    });

    it('includes correct HTTP status code', function () {
        $response = ApiErrorResponse::fromCode(ErrorCode::OrderNotFound);
        expect($response->toResponse()->getStatusCode())->toBe(404);

        $response = ApiErrorResponse::fromCode(ErrorCode::Forbidden);
        expect($response->toResponse()->getStatusCode())->toBe(403);

        $response = ApiErrorResponse::fromCode(ErrorCode::InternalError);
        expect($response->toResponse()->getStatusCode())->toBe(500);
    });

    it('includes retryable flag', function () {
        $retryable = ApiErrorResponse::fromCode(ErrorCode::PaymentGatewayError);
        $json = $retryable->toResponse()->getData(true);
        expect($json['error']['retryable'])->toBeTrue();

        $notRetryable = ApiErrorResponse::fromCode(ErrorCode::Forbidden);
        $json = $notRetryable->toResponse()->getData(true);
        expect($json['error']['retryable'])->toBeFalse();
    });

    it('uses custom message when provided', function () {
        $customMessage = 'This is a custom message';
        $response = ApiErrorResponse::fromCodeWithMessage(
            ErrorCode::ValidationFailed,
            $customMessage
        );
        $json = $response->toResponse()->getData(true);

        expect($json['error']['message'])->toBe($customMessage);
    });

    it('hides exception details in production', function () {
        config(['app.debug' => false]);

        $response = ApiErrorResponse::fromException(
            new Exception('Secret internal error'),
            'test-id'
        );
        $json = $response->toResponse()->getData(true);

        expect($json['error']['message'])->not->toContain('Secret');
        expect($json['error'])->not->toHaveKey('details');
    });
});

describe('PerformanceBudgets', function () {
    it('returns correct budget for checkout route', function () {
        $budget = PerformanceBudgets::forRoute('api.v1.checkout');
        expect($budget)->toBe(PerformanceBudgets::CHECKOUT_MAX_MS);
    });

    it('returns correct budget for payment confirmation', function () {
        $budget = PerformanceBudgets::forRoute('api.v1.checkout.confirm-payment');
        expect($budget)->toBe(PerformanceBudgets::PAYMENT_CONFIRMATION_MAX_MS);
    });

    it('returns default budget for unknown routes', function () {
        $budget = PerformanceBudgets::forRoute('unknown.route');
        expect($budget)->toBe(PerformanceBudgets::DEFAULT_MAX_MS);
    });

    it('correctly identifies exceeded budget', function () {
        expect(PerformanceBudgets::exceedsBudget('api.v1.orders.index', 600))->toBeTrue();
        expect(PerformanceBudgets::exceedsBudget('api.v1.orders.index', 400))->toBeFalse();
    });

    it('defines reasonable budgets', function () {
        expect(PerformanceBudgets::CHECKOUT_MAX_MS)->toBeLessThanOrEqual(5000);
        expect(PerformanceBudgets::ORDER_LIST_MAX_MS)->toBeLessThanOrEqual(1000);
        expect(PerformanceBudgets::DEFAULT_MAX_MS)->toBeLessThanOrEqual(2000);
    });
});
