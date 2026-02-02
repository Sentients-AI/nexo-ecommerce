<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Cart\Exceptions\EmptyCartException;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Responses\ApiErrorResponse;
use Closure;
use DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TransformDomainExceptions
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (AuthenticationException) {
            return $this->errorResponse($request, ErrorCode::Unauthorized);
        } catch (EmptyCartException) {
            return $this->errorResponse($request, ErrorCode::CartEmpty);
        } catch (InsufficientStockException $e) {
            return $this->errorResponse(
                $request,
                ErrorCode::InsufficientStock,
                "Insufficient stock for product {$e->productId}"
            );
        } catch (DomainException $e) {
            return $this->transformDomainException($request, $e);
        }
    }

    private function transformDomainException(Request $request, DomainException $e): Response
    {
        $message = $e->getMessage();

        // Map exception messages to error codes
        $code = match (true) {
            str_contains($message, 'refundable') => ErrorCode::OrderNotRefundable,
            str_contains($message, 'exceeds') => ErrorCode::RefundAmountExceedsLimit,
            str_contains($message, 'empty') => ErrorCode::CartEmpty,
            str_contains($message, 'not found') => ErrorCode::OrderNotFound,
            str_contains($message, 'permission') => ErrorCode::Forbidden,
            str_contains($message, 'payment') => ErrorCode::PaymentFailed,
            default => ErrorCode::ValidationFailed,
        };

        return $this->errorResponse($request, $code, $message);
    }

    private function errorResponse(
        Request $request,
        ErrorCode $code,
        ?string $customMessage = null
    ): Response {
        $correlationId = $request->header(AttachCorrelationId::HEADER_NAME);

        $response = $customMessage
            ? ApiErrorResponse::fromCodeWithMessage($code, $customMessage, $correlationId)
            : ApiErrorResponse::fromCode($code, $correlationId);

        return $response->toResponse();
    }
}
